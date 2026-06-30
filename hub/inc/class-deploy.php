<?php
/**
 * Replanta Hub — Deploy & Distribution
 *
 * Two REST endpoints secured by a deploy token (rphub_deploy_token option):
 *
 *   POST /wp-json/replanta-hub/v1/self-update
 *     → Downloads latest Hub release from GitHub and replaces plugin files.
 *
 *   POST /wp-json/replanta-hub/v1/deploy/care  { "version": "1.7.3" }
 *     → Downloads that Care release zip from GitHub, stores in uploads,
 *       and refreshes care-info.json so all clients see the update.
 *
 * Call both from GitHub Actions with:
 *   -H "X-Deploy-Token: <rphub_deploy_token option value>"
 *
 * @since 1.8.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class ReplantaHub_Deploy {

    private const CARE_REPO  = 'replantadev/care';
    private const HUB_REPO   = 'replantadev/hub';
    private const UPDATES_DIR = 'replanta-updates';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        $ns = 'replanta-hub/v1';

        register_rest_route($ns, '/self-update', [
            'methods'             => 'POST',
            'callback'            => [$this, 'self_update'],
            'permission_callback' => [$this, 'check_deploy_token'],
        ]);

        register_rest_route($ns, '/deploy/care', [
            'methods'             => 'POST',
            'callback'            => [$this, 'deploy_care'],
            'permission_callback' => [$this, 'check_deploy_token'],
            'args'                => [
                'version' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        // Public endpoint: Care sites call this to check for updates
        register_rest_route($ns, '/updates/care', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_care_update_info'],
            'permission_callback' => '__return_true',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Permission                                                         */
    /* ------------------------------------------------------------------ */

    public function check_deploy_token(\WP_REST_Request $request) {
        $token = get_option('rphub_deploy_token', '');
        if (empty($token)) {
            return new \WP_Error('no_token', 'Deploy token not configured', ['status' => 503]);
        }
        $provided = $request->get_header('X-Deploy-Token');
        if (!hash_equals($token, (string) $provided)) {
            return new \WP_Error('forbidden', 'Invalid deploy token', ['status' => 403]);
        }
        return true;
    }

    /* ------------------------------------------------------------------ */
    /*  POST /self-update — update the Hub plugin itself                  */
    /* ------------------------------------------------------------------ */

    public function self_update(\WP_REST_Request $request) {
        $release = $this->github_latest_release(self::HUB_REPO);
        if (is_wp_error($release)) {
            return $release;
        }

        $version  = ltrim($release['tag_name'] ?? '', 'v');
        $zip_url  = $this->get_release_asset_url($release, 'replanta-hub');
        $is_zipball = false;

        if (!$zip_url) {
            // Fallback: use GitHub source zipball when no release asset is attached
            $zip_url = $release['zipball_url'] ?? '';
            $is_zipball = true;
        }

        if (!$zip_url) {
            return new \WP_Error('no_asset', 'No zip asset or zipball found in Hub release', ['status' => 404]);
        }

        $tmp = $this->download_github_asset($zip_url, $is_zipball);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $plugin_dir = WP_PLUGIN_DIR . '/replanta-hub';
        $result     = $this->extract_zip_over($tmp, $plugin_dir, 'replanta-hub');
        @unlink($tmp);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success'  => true,
            'version'  => $version,
            'message'  => "Hub actualizado a v{$version}",
            'deployed' => current_time('mysql'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  POST /deploy/care — cache Care zip and refresh care-info.json     */
    /* ------------------------------------------------------------------ */

    public function deploy_care(\WP_REST_Request $request) {
        $version = $request->get_param('version');
        $tag     = 'v' . ltrim($version, 'v');

        $release = $this->github_release_by_tag(self::CARE_REPO, $tag);
        if (is_wp_error($release)) {
            return $release;
        }

        $zip_url = $this->get_release_asset_url($release, 'replanta-care');
        if (!$zip_url) {
            return new \WP_Error('no_asset', "No zip found in Care release {$tag}", ['status' => 404]);
        }

        // Download zip from GitHub
        $tmp = $this->download_github_asset($zip_url);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        // Save to uploads/replanta-updates/
        $uploads    = wp_upload_dir();
        $target_dir = $uploads['basedir'] . '/' . self::UPDATES_DIR;
        wp_mkdir_p($target_dir);

        $zip_filename = "replanta-care-{$version}.zip";
        $zip_dest     = $target_dir . '/' . $zip_filename;

        if (!rename($tmp, $zip_dest)) {
            copy($tmp, $zip_dest);
            @unlink($tmp);
        }

        // Write care-info.json
        $info = [
            'name'         => 'Replanta Care',
            'slug'         => 'replanta-care',
            'version'      => $version,
            'download_url' => $uploads['baseurl'] . '/' . self::UPDATES_DIR . '/' . $zip_filename,
            'author'       => 'Replanta',
            'author_homepage' => 'https://replanta.dev',
            'requires'     => '6.0',
            'tested'       => '6.7',
            'requires_php' => '7.4',
            'last_updated' => gmdate('Y-m-d H:i:s'),
            'sections'     => [
                'description' => 'Plugin de mantenimiento automático para clientes de Replanta.',
                'changelog'   => "Versión {$version}.",
            ],
        ];

        file_put_contents(
            $target_dir . '/care-info.json',
            json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        // Store latest version in DB for the public endpoint
        update_option('rphub_care_latest_version', $version);

        return rest_ensure_response([
            'success'      => true,
            'version'      => $version,
            'download_url' => $info['download_url'],
            'deployed'     => current_time('mysql'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  GET /updates/care — public update check for Care clients          */
    /* ------------------------------------------------------------------ */

    public function get_care_update_info() {
        $uploads  = wp_upload_dir();
        $json_path = $uploads['basedir'] . '/' . self::UPDATES_DIR . '/care-info.json';

        if (!file_exists($json_path)) {
            return new \WP_Error('not_found', 'No care-info.json found', ['status' => 404]);
        }

        $raw  = file_get_contents($json_path);
        $info = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $raw), true);

        if (!is_array($info)) {
            return new \WP_Error('invalid_json', 'care-info.json is corrupt', ['status' => 500]);
        }

        // Serve the JSON manually: any stray output emitted during bootstrap
        // (e.g. a BOM from a misencoded PHP file) would otherwise prefix the
        // REST response and break PUC's json_decode on client sites.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            status_header(200);
            header('Content-Type: application/json; charset=utf-8');
            nocache_headers();
        }
        echo wp_json_encode($info);
        exit;
    }

    /* ------------------------------------------------------------------ */
    /*  GitHub API helpers                                                */
    /* ------------------------------------------------------------------ */

    private function github_latest_release($repo) {
        return $this->github_api("repos/{$repo}/releases/latest");
    }

    private function github_release_by_tag($repo, $tag) {
        return $this->github_api("repos/{$repo}/releases/tags/{$tag}");
    }

    private function github_api($path) {
        $token = get_option('rphub_github_token', '');
        $args  = [
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'Replanta-Hub/' . (defined('RPHUB_VERSION') ? RPHUB_VERSION : '1'),
            ],
            'timeout' => 20,
        ];
        if (!empty($token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get("https://api.github.com/{$path}", $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            return new \WP_Error(
                'github_api_error',
                "GitHub API error {$code}: " . ($body['message'] ?? 'Unknown error'),
                ['status' => $code === 404 ? 404 : 502]
            );
        }

        return $body;
    }

    private function get_release_asset_url($release, $slug_prefix) {
        foreach ($release['assets'] ?? [] as $asset) {
            if (strpos($asset['name'], $slug_prefix) === 0 && substr($asset['name'], -4) === '.zip') {
                return $asset['url']; // API URL — requires auth + Accept: octet-stream
            }
        }
        return null;
    }

    private function download_github_asset($asset_api_url, $is_zipball = false) {
        $token = get_option('rphub_github_token', '');
        $args  = [
            'headers' => [
                'Accept'     => $is_zipball ? 'application/vnd.github+json' : 'application/octet-stream',
                'User-Agent' => 'Replanta-Hub/' . (defined('RPHUB_VERSION') ? RPHUB_VERSION : '1'),
            ],
            'timeout'  => 120,
            'stream'   => true,
            'filename' => wp_tempnam('rphub-deploy-'),
        ];
        if (!empty($token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get($asset_api_url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            @unlink($response['filename'] ?? '');
            return new \WP_Error('download_failed', "Asset download failed ({$code})", ['status' => 502]);
        }

        return $response['filename'];
    }

    /* ------------------------------------------------------------------ */
    /*  Zip extraction                                                     */
    /* ------------------------------------------------------------------ */

    private function extract_zip_over($zip_path, $target_dir, $inner_folder) {
        if (!class_exists('ZipArchive')) {
            return new \WP_Error('no_zip', 'ZipArchive not available');
        }

        $zip = new \ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return new \WP_Error('bad_zip', 'Cannot open zip file');
        }

        $tmp_extract = wp_tempnam('rphub-extract-');
        @unlink($tmp_extract);
        wp_mkdir_p($tmp_extract);

        $zip->extractTo($tmp_extract);
        $zip->close();

        // Zip contains replanta-hub/ folder — move contents over
        $source = trailingslashit($tmp_extract) . trailingslashit($inner_folder);
        if (!is_dir($source)) {
            // Fallback: find first directory inside tmp_extract
            $dirs   = glob($tmp_extract . '/*', GLOB_ONLYDIR);
            $source = !empty($dirs) ? trailingslashit($dirs[0]) : $tmp_extract . '/';
        }

        // Copy files to target, skipping currently executing file
        $this->recursive_copy($source, trailingslashit($target_dir));

        // Cleanup
        $this->recursive_rmdir($tmp_extract);

        return true;
    }

    private function recursive_copy($src, $dst) {
        $dir = opendir($src);
        wp_mkdir_p($dst);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $s = $src . $file;
            $d = $dst . $file;
            if (is_dir($s)) {
                $this->recursive_copy($s . '/', $d . '/');
            } else {
                @copy($s, $d);
            }
        }
        closedir($dir);
    }

    private function recursive_rmdir($dir) {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->recursive_rmdir($file) : @unlink($file);
        }
        @rmdir($dir);
    }
}
