<?php
/**
 * Replanta Hub — Metrics fetchers.
 *
 * Each method receives a site_token + window (default 30d) and returns a
 * normalised array consumable by Care. Failures return WP_Error.
 *
 * Caching: results cached per (site_token, type, window) for 30 min via transient.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Metrics {

    const CACHE_TTL = 30 * MINUTE_IN_SECONDS;

    public static function ga4($site_token, $days = 30) {
        $cache_key = self::ck('ga4', $site_token, $days);
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $mapping = RPHUB_Integrations::get_site_mapping($site_token);
        if (empty($mapping['integrations']['ga4_property_id'])) {
            return new WP_Error('not_mapped', 'GA4 no asignado para este sitio');
        }
        $property = $mapping['integrations']['ga4_property_id'];
        $token = RPHUB_Integrations::get_google_access_token();
        if (is_wp_error($token)) return $token;

        $body = [
            'dateRanges' => [['startDate' => $days . 'daysAgo', 'endDate' => 'today']],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'screenPageViews'],
                ['name' => 'bounceRate'],
                ['name' => 'averageSessionDuration'],
            ],
        ];
        $top_pages = [
            'dateRanges' => [['startDate' => $days . 'daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'pagePath']],
            'metrics'    => [['name' => 'screenPageViews']],
            'orderBys'   => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
            'limit'      => 10,
        ];

        $totals = self::ga4_run_report($property, $token, $body);
        if (is_wp_error($totals)) return $totals;
        $top = self::ga4_run_report($property, $token, $top_pages);
        if (is_wp_error($top)) $top = ['rows' => []];

        $row = $totals['rows'][0]['metricValues'] ?? [];
        $payload = [
            'window_days' => $days,
            'sessions'    => isset($row[0]['value']) ? (int) $row[0]['value'] : 0,
            'users'       => isset($row[1]['value']) ? (int) $row[1]['value'] : 0,
            'pageviews'   => isset($row[2]['value']) ? (int) $row[2]['value'] : 0,
            'bounce_rate' => isset($row[3]['value']) ? round((float) $row[3]['value'] * 100, 1) : null,
            'avg_session_sec' => isset($row[4]['value']) ? round((float) $row[4]['value'], 1) : null,
            'top_pages'   => array_map(function($r){
                return [
                    'path'      => $r['dimensionValues'][0]['value'] ?? '',
                    'pageviews' => (int) ($r['metricValues'][0]['value'] ?? 0),
                ];
            }, $top['rows'] ?? []),
            'fetched_at'  => current_time('mysql'),
        ];

        set_transient($cache_key, $payload, self::CACHE_TTL);
        return $payload;
    }

    private static function ga4_run_report($property_id, $token, $body) {
        $url = sprintf('https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport', rawurlencode($property_id));
        $r = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);
        if (is_wp_error($r)) return $r;
        $code = wp_remote_retrieve_response_code($r);
        $decoded = json_decode(wp_remote_retrieve_body($r), true);
        if ($code >= 400) {
            return new WP_Error('ga4_' . $code, $decoded['error']['message'] ?? 'GA4 HTTP ' . $code);
        }
        return $decoded;
    }

    public static function search_console($site_token, $days = 30) {
        $cache_key = self::ck('sc', $site_token, $days);
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $mapping = RPHUB_Integrations::get_site_mapping($site_token);
        if (empty($mapping['integrations']['sc_site_url'])) {
            return new WP_Error('not_mapped', 'Search Console no asignado');
        }
        $site_url = $mapping['integrations']['sc_site_url'];
        $token = RPHUB_Integrations::get_google_access_token();
        if (is_wp_error($token)) return $token;

        $end   = gmdate('Y-m-d');
        $start = gmdate('Y-m-d', strtotime("-{$days} days"));
        $api = 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($site_url) . '/searchAnalytics/query';

        $totals = self::sc_query($api, $token, [
            'startDate' => $start,
            'endDate'   => $end,
            'rowLimit'  => 1,
        ]);
        if (is_wp_error($totals)) return $totals;

        $queries = self::sc_query($api, $token, [
            'startDate' => $start,
            'endDate'   => $end,
            'dimensions'=> ['query'],
            'rowLimit'  => 10,
        ]);
        if (is_wp_error($queries)) $queries = ['rows' => []];

        $pages = self::sc_query($api, $token, [
            'startDate' => $start,
            'endDate'   => $end,
            'dimensions'=> ['page'],
            'rowLimit'  => 10,
        ]);
        if (is_wp_error($pages)) $pages = ['rows' => []];

        $t = $totals['rows'][0] ?? [];
        $payload = [
            'window_days' => $days,
            'clicks'      => (int) ($t['clicks'] ?? 0),
            'impressions' => (int) ($t['impressions'] ?? 0),
            'ctr'         => isset($t['ctr']) ? round((float)$t['ctr'] * 100, 2) : null,
            'position'    => isset($t['position']) ? round((float)$t['position'], 1) : null,
            'top_queries' => array_map([__CLASS__, 'sc_normalize_row'], $queries['rows'] ?? []),
            'top_pages'   => array_map([__CLASS__, 'sc_normalize_row'], $pages['rows'] ?? []),
            'fetched_at'  => current_time('mysql'),
        ];

        set_transient($cache_key, $payload, self::CACHE_TTL);
        return $payload;
    }

    private static function sc_query($api, $token, $body) {
        $r = wp_remote_post($api, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);
        if (is_wp_error($r)) return $r;
        $code = wp_remote_retrieve_response_code($r);
        $decoded = json_decode(wp_remote_retrieve_body($r), true);
        if ($code >= 400) {
            return new WP_Error('sc_' . $code, $decoded['error']['message'] ?? 'SC HTTP ' . $code);
        }
        return $decoded;
    }

    private static function sc_normalize_row($row) {
        return [
            'key'         => $row['keys'][0] ?? '',
            'clicks'      => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'ctr'         => isset($row['ctr']) ? round((float)$row['ctr'] * 100, 2) : null,
            'position'    => isset($row['position']) ? round((float)$row['position'], 1) : null,
        ];
    }

    public static function cloudflare($site_token, $days = 7) {
        $cache_key = self::ck('cf', $site_token, $days);
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $mapping = RPHUB_Integrations::get_site_mapping($site_token);
        if (empty($mapping['integrations']['cf_zone_id'])) {
            return new WP_Error('not_mapped', 'Cloudflare zona no asignada');
        }
        $zone = $mapping['integrations']['cf_zone_id'];
        $token = RPHUB_Integrations::get_cloudflare_token();
        if (empty($token)) return new WP_Error('no_cf_token', 'Cloudflare no configurado');

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ];

        // Zone info (SSL / plan)
        $zr = wp_remote_get("https://api.cloudflare.com/client/v4/zones/{$zone}", ['headers' => $headers, 'timeout' => 20]);
        $zone_info = !is_wp_error($zr) ? json_decode(wp_remote_retrieve_body($zr), true) : null;

        // Analytics dashboard (last $days)
        $url = "https://api.cloudflare.com/client/v4/zones/{$zone}/analytics/dashboard?since=-" . ($days * 1440) . "&until=0&continuous=true";
        $ar = wp_remote_get($url, ['headers' => $headers, 'timeout' => 30]);
        $analytics = !is_wp_error($ar) ? json_decode(wp_remote_retrieve_body($ar), true) : null;
        $tot = $analytics['result']['totals'] ?? [];

        $payload = [
            'window_days' => $days,
            'requests'    => (int) ($tot['requests']['all'] ?? 0),
            'cached'      => (int) ($tot['requests']['cached'] ?? 0),
            'cache_ratio' => self::ratio($tot['requests']['cached'] ?? 0, $tot['requests']['all'] ?? 0),
            'bandwidth_bytes'  => (int) ($tot['bandwidth']['all'] ?? 0),
            'threats'     => (int) ($tot['threats']['all'] ?? 0),
            'uniques'     => (int) ($tot['uniques']['all'] ?? 0),
            'zone' => [
                'name'   => $zone_info['result']['name'] ?? '',
                'status' => $zone_info['result']['status'] ?? '',
                'plan'   => $zone_info['result']['plan']['name'] ?? '',
                'ssl'    => $zone_info['result']['ssl']['status'] ?? '',
            ],
            'fetched_at'  => current_time('mysql'),
        ];

        set_transient($cache_key, $payload, self::CACHE_TTL);
        return $payload;
    }

    private static function ratio($num, $den) {
        $den = (int) $den;
        if ($den <= 0) return null;
        return round(((int)$num) / $den * 100, 2);
    }

    private static function ck($type, $site_token, $days) {
        return 'rphub_metrics_' . $type . '_' . md5($site_token) . '_' . (int) $days;
    }

    public static function flush_cache($site_token = null) {
        global $wpdb;
        if ($site_token) {
            $prefix = '_transient_rphub_metrics_';
            $hash = md5($site_token);
            $like = $wpdb->esc_like($prefix) . '%' . $wpdb->esc_like($hash) . '%';
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
        } else {
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rphub_metrics_%'");
        }
    }
}
