<?php
/**
 * Admin Ecological Portfolio Dashboard
 * Shows Tree-Nation data and per-domain ecological impact from Dominios Reseller.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Admin_Ecological {

    public function render() {
        global $wpdb;

        $t_dr    = $wpdb->prefix . 'dominios_reseller';
        $t_sites = $wpdb->prefix . 'rphub_sites';
        $dr_ok   = (bool) $wpdb->get_var("SHOW TABLES LIKE '$t_dr'");

        // Portfolio totals — try Tree-Nation cached transient first
        $tn_data = get_transient('dr_tn_public_totals');
        if (!$tn_data) {
            if ($dr_ok) {
                $row = $wpdb->get_row(
                    "SELECT COALESCE(SUM(trees_planted),0) AS trees, COALESCE(SUM(co2_evaded),0) AS co2
                     FROM `$t_dr` WHERE is_primary = 1"
                );
                $tn_data = [
                    'trees'  => (int)   ($row->trees ?? 0),
                    'co2'    => (float) ($row->co2   ?? 0),
                    'source' => 'db',
                ];
            } else {
                $tn_data = ['trees' => 0, 'co2' => 0, 'source' => 'unavailable'];
            }
        }

        // Per-domain breakdown (only primary, with impact data)
        $domains = $dr_ok ? $wpdb->get_results(
            "SELECT domain, COALESCE(primary_domain, domain) AS primary_domain,
                    trees_planted, co2_evaded, startdate, status
             FROM `$t_dr`
             WHERE is_primary = 1 AND (trees_planted > 0 OR co2_evaded > 0)
             ORDER BY trees_planted DESC, co2_evaded DESC
             LIMIT 50",
            ARRAY_A
        ) : [];

        // Build domain→site name map
        $site_map = [];
        if ($domains) {
            $all_sites = $wpdb->get_results(
                "SELECT name, url FROM $t_sites WHERE status = 'active'",
                ARRAY_A
            ) ?: [];
            foreach ($all_sites as $r) {
                $host = strtolower(preg_replace('/^www\./i', '', parse_url($r['url'], PHP_URL_HOST) ?: ''));
                $site_map[$host] = $r['name'];
            }
        }

        $total_trees  = (int)   $tn_data['trees'];
        $total_co2    = (float) $tn_data['co2'];
        $source_label = $tn_data['source'] === 'api' ? 'Tree-Nation API' : 'Base de datos local';
        $sites_count  = count($domains);

        // SVG bar chart (top 15 by trees)
        $chart_data = array_slice($domains, 0, 15);
        $max_trees  = max(1, (int) ($chart_data[0]['trees_planted'] ?? 1));
        ?>
        <div class="wrap rphub-ecological">
            <h1>Ecosistema — Portfolio Ecológico</h1>
            <p class="description" style="margin-bottom:16px;">
                Impacto medioambiental acumulado de todos los sitios gestionados.
                Fuente: <strong><?php echo esc_html($source_label); ?></strong>
                <?php if ($tn_data['source'] === 'api'): ?>
                    <span class="rphub-badge rphub-badge-green" style="font-size:11px;padding:2px 6px;border-radius:8px;background:#d4edda;color:#155724;margin-left:6px;">Live</span>
                <?php endif; ?>
            </p>

            <!-- Hero KPIs -->
            <div class="rphub-eco-hero">
                <div class="rphub-eco-kpi">
                    <div class="rphub-eco-kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22V12"/><path d="M12 12C12 7 8 4 4 4c0 4 3 8 8 8z"/><path d="M12 12c0-5 4-8 8-8 0 4-3 8-8 8z"/></svg></div>
                    <div class="rphub-eco-kpi-value"><?php echo number_format($total_trees); ?></div>
                    <div class="rphub-eco-kpi-label">Árboles plantados</div>
                </div>
                <div class="rphub-eco-kpi">
                    <div class="rphub-eco-kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9.59 4.59A2 2 0 1 1 11 8H2m10.59 11.41A2 2 0 1 0 14 16H2m15.73-8.27A2.5 2.5 0 1 1 19.5 12H2"/></svg></div>
                    <div class="rphub-eco-kpi-value"><?php echo number_format($total_co2, 1); ?> <small>kg</small></div>
                    <div class="rphub-eco-kpi-label">CO₂ compensado</div>
                </div>
                <div class="rphub-eco-kpi">
                    <div class="rphub-eco-kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
                    <div class="rphub-eco-kpi-value"><?php echo esc_html($sites_count); ?></div>
                    <div class="rphub-eco-kpi-label">Sitios con impacto</div>
                </div>
                <?php if ($total_trees > 0): ?>
                <div class="rphub-eco-kpi">
                    <div class="rphub-eco-kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg></div>
                    <div class="rphub-eco-kpi-value"><?php echo number_format($total_co2 / max(1, $total_trees), 1); ?> <small>kg</small></div>
                    <div class="rphub-eco-kpi-label">CO₂ / árbol (promedio)</div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($chart_data): ?>
            <!-- Bar chart -->
            <h2 style="margin-top:24px;">Distribución por dominio (top <?php echo count($chart_data); ?>)</h2>
            <div class="rphub-eco-chart-wrap">
                <?php echo $this->render_bar_chart($chart_data, $max_trees); ?>
            </div>
            <?php endif; ?>

            <!-- Domain table -->
            <?php if ($domains): ?>
            <h2 style="margin-top:24px;">Detalle por dominio</h2>
            <table class="wp-list-table widefat fixed striped rphub-eco-table">
                <thead>
                    <tr>
                        <th>Dominio</th>
                        <th>Sitio en Hub</th>
                        <th style="width:110px;text-align:right;">Árboles</th>
                        <th style="width:130px;text-align:right;">CO₂ (kg)</th>
                        <th style="width:110px;text-align:right;">Días activo</th>
                        <th style="width:80px;text-align:center;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($domains as $d):
                        $dom  = $d['primary_domain'] ?: $d['domain'];
                        $site = $site_map[$dom] ?? ($site_map['www.' . $dom] ?? '—');
                        $trees = (int) $d['trees_planted'];
                        $co2   = (float) $d['co2_evaded'];
                        $days  = $d['startdate'] ? max(0, (int) ceil((time() - (int)$d['startdate']) / 86400)) : 0;
                        $active = (strtolower($d['status'] ?? '') === 'activo');
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($dom); ?></strong></td>
                        <td style="color:<?php echo $site === '—' ? '#999' : '#000'; ?>;"><?php echo esc_html($site); ?></td>
                        <td style="text-align:right;"><?php echo $trees > 0 ? esc_html(number_format($trees)) . ' 🌱' : '—'; ?></td>
                        <td style="text-align:right;"><?php echo $co2 > 0 ? esc_html(number_format($co2, 1)) : '—'; ?></td>
                        <td style="text-align:right;"><?php echo $days > 0 ? esc_html(number_format($days)) : '—'; ?></td>
                        <td style="text-align:center;">
                            <span class="rphub-eco-status <?php echo $active ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $active ? 'Activo' : esc_html($d['status'] ?? '—'); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color:#666;font-style:italic;margin-top:20px;">
                No se encontraron datos ecológicos en la base de datos de Dominios Reseller.
            </p>
            <?php endif; ?>
        </div>

        <style>
        .rphub-ecological h1 { margin-bottom: 4px; }
        .rphub-eco-hero {
            display: flex; gap: 16px; flex-wrap: wrap; margin: 16px 0;
        }
        .rphub-eco-kpi {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 16px 20px; min-width: 160px; text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        .rphub-eco-kpi-icon { font-size: 28px; line-height: 1; margin-bottom: 4px; }
        .rphub-eco-kpi-value { font-size: 28px; font-weight: 700; color: #1d4ed8; line-height: 1.2; }
        .rphub-eco-kpi-value small { font-size: 14px; font-weight: 400; color: #64748b; }
        .rphub-eco-kpi-label { font-size: 12px; color: #64748b; margin-top: 4px; }
        .rphub-eco-chart-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; overflow-x: auto; }
        .rphub-eco-table td, .rphub-eco-table th { vertical-align: middle; }
        .rphub-eco-status { font-size: 11px; padding: 2px 8px; border-radius: 10px; }
        .rphub-eco-status.status-active   { background: #d1fae5; color: #065f46; }
        .rphub-eco-status.status-inactive { background: #f1f5f9; color: #64748b; }
        </style>
        <?php
    }

    private function render_bar_chart(array $data, int $max_trees): string {
        $bar_width  = 460;
        $bar_height = 22;
        $row_gap    = 6;
        $label_w    = 200;
        $val_w      = 55;
        $total_h    = count($data) * ($bar_height + $row_gap) + 20;
        $svg_w      = $label_w + $bar_width + $val_w + 20;

        $out = "<svg xmlns='http://www.w3.org/2000/svg' width='$svg_w' height='$total_h' font-family='sans-serif' font-size='12'>";

        foreach ($data as $i => $d) {
            $trees = (int) $d['trees_planted'];
            $dom   = $d['primary_domain'] ?: $d['domain'];
            $y     = $i * ($bar_height + $row_gap) + 10;
            $bw    = $max_trees > 0 ? (int) round($trees / $max_trees * $bar_width) : 0;
            $bw    = max(2, $bw);

            // Label
            $label = strlen($dom) > 28 ? substr($dom, 0, 26) . '…' : $dom;
            $out  .= "<text x='$label_w' y='" . ($y + $bar_height - 6) . "' text-anchor='end' fill='#374151'>" . esc_html($label) . "</text>";

            // Bar
            $out .= "<rect x='" . ($label_w + 6) . "' y='$y' width='$bw' height='$bar_height' rx='3' fill='#4ade80' opacity='.85'/>";

            // Value
            $vx = $label_w + 6 + $bw + 6;
            $out .= "<text x='$vx' y='" . ($y + $bar_height - 6) . "' fill='#065f46' font-weight='600'>" . number_format($trees) . "</text>";
        }

        $out .= '</svg>';
        return $out;
    }
}
