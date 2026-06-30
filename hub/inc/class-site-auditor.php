<?php
if (!defined('ABSPATH')) exit;

class RPHUB_Site_Auditor {

    public static function run_audit(int $site_id, bool $force = false): array {
        if (!$force) {
            $last = RPHUB_Database::get_site_meta($site_id, 'audit_last_run');
            if ($last && strtotime($last) > (time() - DAY_IN_SECONDS)) {
                return [
                    'cached'     => true,
                    'cf_score'   => (int) RPHUB_Database::get_site_meta($site_id, 'cf_score'),
                    'seo_score'  => (int) RPHUB_Database::get_site_meta($site_id, 'seo_score'),
                    'perf_score' => (int) RPHUB_Database::get_site_meta($site_id, 'perf_score'),
                    'last_run'   => $last,
                ];
            }
        }

        global $wpdb;
        $site = $wpdb->get_row(
            $wpdb->prepare("SELECT url FROM {$wpdb->prefix}rphub_sites WHERE id = %d", $site_id)
        );

        if (!$site) {
            return ['error' => 'Sitio no encontrado'];
        }

        $result = [
            'cached'    => false,
            'site_id'   => $site_id,
            'cf'        => null,
            'seo'       => null,
            'perf'      => null,
        ];

        // CF audit — only if zone_id available
        $cf_zone_id = RPHUB_Database::get_site_meta($site_id, 'cf_zone_id');
        if ($cf_zone_id && class_exists('RPHUB_CF_Audit') && class_exists('Dominios_Reseller_Cloudflare_Service')) {
            $cf_audit     = new RPHUB_CF_Audit($cf_zone_id);
            $cf_result    = $cf_audit->run();
            $result['cf'] = $cf_result;
            RPHUB_Database::update_site_meta($site_id, 'cf_score', $cf_result['score'] ?? 0);
            RPHUB_Database::update_site_meta($site_id, 'cf_issues_json', wp_json_encode($cf_result['checks'] ?? []));
        }

        // SEO audit
        if (class_exists('RPHUB_SEO_Audit')) {
            $seo_audit      = new RPHUB_SEO_Audit($site->url);
            $seo_result     = $seo_audit->run();
            $result['seo']  = $seo_result;
            RPHUB_Database::update_site_meta($site_id, 'seo_score', $seo_result['score'] ?? 0);
            RPHUB_Database::update_site_meta($site_id, 'seo_issues_json', wp_json_encode($seo_result['checks'] ?? []));
        }

        // Performance audit (PSI)
        if (class_exists('RPHUB_Perf_Audit')) {
            $psi_key        = get_option('rphub_pagespeed_api_key', '');
            $perf_audit     = new RPHUB_Perf_Audit($site->url, $psi_key);
            $perf_result    = $perf_audit->run();
            $result['perf'] = $perf_result;
            RPHUB_Database::update_site_meta($site_id, 'perf_score', $perf_result['score'] ?? 0);
            RPHUB_Database::update_site_meta($site_id, 'perf_data_json', wp_json_encode($perf_result));
        }

        $now = current_time('mysql');
        RPHUB_Database::update_site_meta($site_id, 'audit_last_run', $now);
        $result['last_run'] = $now;

        return $result;
    }
}
