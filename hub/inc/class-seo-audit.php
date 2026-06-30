<?php
if (!defined('ABSPATH')) exit;

class RPHUB_SEO_Audit {

    private string $site_url;

    public function __construct(string $site_url) {
        $this->site_url = rtrim($site_url, '/');
    }

    public function run(): array {
        $checks = [];

        $home = $this->fetch($this->site_url);
        $robots = $this->fetch($this->site_url . '/robots.txt');

        $checks[] = $this->check_robots($robots);
        $checks[] = $this->check_sitemap();
        $checks[] = $this->check_title($home);
        $checks[] = $this->check_description($home);
        $checks[] = $this->check_og_title($home);
        $checks[] = $this->check_og_image($home);
        $checks[] = $this->check_canonical($home);
        $checks[] = $this->check_h1($home);
        $checks[] = $this->check_schema($home);

        $score = 100;
        foreach ($checks as $c) {
            if ($c['status'] === 'critical') $score -= 15;
            elseif ($c['status'] === 'warning') $score -= 5;
        }

        return ['score' => max(0, $score), 'checks' => $checks];
    }

    private function fetch(string $url): string {
        $r = wp_remote_get($url, ['timeout' => 15, 'sslverify' => false]);
        if (is_wp_error($r)) return '';
        if (wp_remote_retrieve_response_code($r) !== 200) return '';
        return wp_remote_retrieve_body($r);
    }

    private function check_robots(string $body): array {
        if (empty($body)) {
            return ['id' => 'robots_txt', 'label' => 'robots.txt', 'status' => 'warning', 'details' => 'No encontrado'];
        }
        if (preg_match('/Disallow:\s*\//i', $body)) {
            return ['id' => 'robots_txt', 'label' => 'robots.txt', 'status' => 'critical', 'details' => 'Bloquea todo el sitio'];
        }
        return ['id' => 'robots_txt', 'label' => 'robots.txt', 'status' => 'good', 'details' => 'OK'];
    }

    private function check_sitemap(): array {
        $candidates = ['/sitemap.xml', '/sitemap_index.xml', '/wp-sitemap.xml'];
        foreach ($candidates as $path) {
            $r = wp_remote_head($this->site_url . $path, ['timeout' => 10, 'sslverify' => false]);
            if (!is_wp_error($r) && in_array(wp_remote_retrieve_response_code($r), [200, 301, 302], true)) {
                return ['id' => 'sitemap', 'label' => 'Sitemap XML', 'status' => 'good', 'details' => $path];
            }
        }
        return ['id' => 'sitemap', 'label' => 'Sitemap XML', 'status' => 'warning', 'details' => 'No encontrado'];
    }

    private function check_title(string $html): array {
        if (!preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            return ['id' => 'meta_title', 'label' => 'Meta title', 'status' => 'critical', 'details' => 'No encontrado'];
        }
        $len = mb_strlen(strip_tags($m[1]));
        if ($len < 30 || $len > 60) {
            return ['id' => 'meta_title', 'label' => 'Meta title', 'status' => 'warning', 'details' => "{$len} caracteres (ideal 30-60)"];
        }
        return ['id' => 'meta_title', 'label' => 'Meta title', 'status' => 'good', 'details' => "{$len} caracteres"];
    }

    private function check_description(string $html): array {
        if (!preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/is', $html, $m)) {
            return ['id' => 'meta_description', 'label' => 'Meta description', 'status' => 'warning', 'details' => 'No encontrada'];
        }
        $len = mb_strlen($m[1]);
        if ($len < 120 || $len > 160) {
            return ['id' => 'meta_description', 'label' => 'Meta description', 'status' => 'warning', 'details' => "{$len} caracteres (ideal 120-160)"];
        }
        return ['id' => 'meta_description', 'label' => 'Meta description', 'status' => 'good', 'details' => "{$len} caracteres"];
    }

    private function check_og_title(string $html): array {
        $ok = (bool) preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+>/is', $html);
        return ['id' => 'og_title', 'label' => 'OG Title', 'status' => $ok ? 'good' : 'warning', 'details' => $ok ? 'Presente' : 'Ausente'];
    }

    private function check_og_image(string $html): array {
        $ok = (bool) preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+>/is', $html);
        return ['id' => 'og_image', 'label' => 'OG Image', 'status' => $ok ? 'good' : 'warning', 'details' => $ok ? 'Presente' : 'Ausente'];
    }

    private function check_canonical(string $html): array {
        $ok = (bool) preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+>/is', $html);
        return ['id' => 'canonical', 'label' => 'URL Canónica', 'status' => $ok ? 'good' : 'warning', 'details' => $ok ? 'Presente' : 'Ausente'];
    }

    private function check_h1(string $html): array {
        preg_match_all('/<h1[^>]*>/is', $html, $m);
        $count = count($m[0]);
        if ($count === 0) {
            return ['id' => 'h1', 'label' => 'Etiqueta H1', 'status' => 'critical', 'details' => 'No encontrada'];
        }
        if ($count > 1) {
            return ['id' => 'h1', 'label' => 'Etiqueta H1', 'status' => 'warning', 'details' => "{$count} H1 encontrados (debe ser 1)"];
        }
        return ['id' => 'h1', 'label' => 'Etiqueta H1', 'status' => 'good', 'details' => '1 H1'];
    }

    private function check_schema(string $html): array {
        $ok = (bool) preg_match('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>/is', $html);
        return ['id' => 'schema_json_ld', 'label' => 'Schema JSON-LD', 'status' => $ok ? 'good' : 'info', 'details' => $ok ? 'Presente' : 'Ausente'];
    }
}
