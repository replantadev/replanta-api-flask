<?php
/**
 * Risk Scorer — evalúa el riesgo de actualizaciones de plugins
 * usando Claude AI para analizar changelogs antes de aplicar updates.
 *
 * Devuelve risk_score (0.0–1.0), risk_factors[] y recommendation
 * (update_direct / update_staging / defer) para cada plugin pendiente.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Risk_Scorer {

    private static $instance = null;

    const CACHE_TTL    = 21600; // 6h — mismo par de versiones no cambia
    const CLAUDE_MODEL = 'claude-haiku-4-5-20251001'; // costo-eficiente para scoring masivo
    const WP_API_BASE  = 'https://api.wordpress.org/plugins/info/1.0/';

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_rphub_risk_score_plugin', [$this, 'ajax_score_plugin']);
        add_action('wp_ajax_rphub_risk_fleet_summary', [$this, 'ajax_fleet_summary']);
    }

    // -------------------------------------------------------------------------
    // API pública
    // -------------------------------------------------------------------------

    /**
     * Evalúa el riesgo de una actualización concreta.
     *
     * @param string $slug         Plugin slug (e.g. "woocommerce")
     * @param string $from_version Versión actualmente instalada
     * @param string $to_version   Versión disponible
     * @return array|WP_Error {
     *   risk_score    float    0.0–1.0
     *   risk_factors  string[]
     *   recommendation string  update_direct|update_staging|defer
     *   summary       string
     *   cached        bool
     * }
     */
    public function score_update($slug, $from_version, $to_version) {
        $cache_key = 'rphub_risk_' . md5("{$slug}_{$from_version}_{$to_version}");

        $cached = get_transient($cache_key);
        if ($cached !== false) {
            $cached['cached'] = true;
            return $cached;
        }

        $changelog = $this->fetch_changelog($slug, $to_version);
        if (is_wp_error($changelog)) {
            // No changelog → score 0.3 conservador (riesgo moderado-bajo sin info)
            $result = $this->fallback_score($slug, $from_version, $to_version, $changelog->get_error_message());
        } else {
            $result = $this->call_claude($slug, $from_version, $to_version, $changelog);
            if (is_wp_error($result)) {
                $result = $this->fallback_score($slug, $from_version, $to_version, $result->get_error_message());
            }
        }

        $result['cached'] = false;
        set_transient($cache_key, $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * Evalúa todos los plugins pendientes de un sitio.
     * Devuelve array con clave = plugin slug.
     */
    public function score_site_updates($site_id, $pending_updates) {
        if (empty($pending_updates['updates']['plugins'])) {
            return [];
        }

        $assessments = [];

        foreach ($pending_updates['updates']['plugins'] as $plugin) {
            $slug         = $plugin['slug']            ?? '';
            $from_version = $plugin['current_version'] ?? '';
            $to_version   = $plugin['new_version']     ?? '';

            if (!$slug || !$from_version || !$to_version) {
                continue;
            }

            $result = $this->score_update($slug, $from_version, $to_version);

            // Si score_update devuelve WP_Error (no debería, pero por si acaso)
            $assessments[$slug] = is_wp_error($result)
                ? $this->fallback_score($slug, $from_version, $to_version, $result->get_error_message())
                : $result;
        }

        return $assessments;
    }

    // -------------------------------------------------------------------------
    // Fetch changelog desde WordPress.org
    // -------------------------------------------------------------------------

    private function fetch_changelog($slug, $to_version) {
        $url = self::WP_API_BASE . sanitize_key($slug) . '.json';

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('wp_api_error', "WordPress.org API HTTP {$code} para {$slug}");
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!$body) {
            return new WP_Error('wp_api_parse', "No se pudo parsear respuesta de WordPress.org para {$slug}");
        }

        $full = strip_tags($body['sections']['changelog'] ?? '');
        if (!$full) {
            return new WP_Error('no_changelog', "Sin changelog en WordPress.org para {$slug} {$to_version}");
        }

        $relevant = $this->extract_version_section($full, $to_version);

        return $relevant ?: substr($full, 0, 2000);
    }

    private function extract_version_section($changelog, $version) {
        $v = preg_quote($version, '/');
        // Busca encabezados tipo "= 1.2.3 =", "## 1.2.3", "* 1.2.3" y captura el texto hasta la siguiente versión
        if (preg_match("/[=\*\#\s]{0,4}{$v}[=\*\#\s]{0,4}(.{0,3000}?)(?=[=\*\#]{1,4}\s*\d+\.\d|\Z)/is", $changelog, $m)) {
            return trim($m[0]);
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Claude API
    // -------------------------------------------------------------------------

    private function get_api_key() {
        $settings = get_option('rphub_settings', []);
        $key = $settings['anthropic_api_key'] ?? '';

        if (!$key && defined('RPHUB_ANTHROPIC_API_KEY')) {
            $key = RPHUB_ANTHROPIC_API_KEY;
        }
        if (!$key) {
            $key = getenv('ANTHROPIC_API_KEY') ?: '';
        }

        return $key;
    }

    private function call_claude($slug, $from, $to, $changelog) {
        $api_key = $this->get_api_key();
        if (!$api_key) {
            return new WP_Error('no_api_key', 'Anthropic API key no configurada en rphub_settings[anthropic_api_key]');
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 30,
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => self::CLAUDE_MODEL,
                'max_tokens' => 512,
                'messages'   => [
                    ['role' => 'user', 'content' => $this->build_prompt($slug, $from, $to, $changelog)],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error(
                'claude_api_error',
                "Claude API HTTP {$code}: " . substr(wp_remote_retrieve_body($response), 0, 200)
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $text = $body['content'][0]['text'] ?? '';

        return $this->parse_claude_response($text, $slug);
    }

    private function build_prompt($slug, $from, $to, $changelog) {
        $changelog_trimmed = substr($changelog, 0, 3000);

        return <<<PROMPT
Analiza el siguiente changelog del plugin de WordPress "{$slug}" (actualización: {$from} → {$to}).

CHANGELOG:
{$changelog_trimmed}

Evalúa el riesgo de aplicar esta actualización en un sitio de producción WordPress. Responde ÚNICAMENTE con un JSON válido con esta estructura:
{
  "risk_score": <float 0.0-1.0>,
  "risk_factors": [<array de strings con factores de riesgo detectados>],
  "recommendation": "<update_direct|update_staging|defer>",
  "summary": "<1-2 frases resumiendo el riesgo>"
}

Criterios para risk_score:
- 0.0–0.3: parches/bugfixes menores, sin cambios de DB ni hooks, retrocompatible
- 0.3–0.5: nuevas features, posibles conflictos menores, sin breaking changes explícitos
- 0.5–0.7: cambios de DB, hooks deprecados, restructuración relevante, testing recomendado
- 0.7–1.0: breaking changes confirmados, migración requerida, incompatibilidades conocidas, changelog advierte rollback

Criterios para recommendation:
- update_direct: risk_score < 0.4 (seguro para producción directa)
- update_staging: risk_score 0.4–0.65 (probar en staging primero)
- defer: risk_score > 0.65 o breaking changes explícitos (posponer hasta revisar manualmente)

Responde SOLO con el JSON, sin markdown ni texto adicional.
PROMPT;
    }

    private function parse_claude_response($text, $slug = '') {
        $text = trim($text);

        // Extraer JSON aunque Claude añada backticks o texto extra
        if (preg_match('/\{[\s\S]+\}/m', $text, $m)) {
            $data = json_decode($m[0], true);
        } else {
            $data = null;
        }

        if (!$data || !isset($data['risk_score'])) {
            return new WP_Error('parse_error', "No se pudo parsear respuesta de Claude para {$slug}: " . substr($text, 0, 200));
        }

        $rec_valid = ['update_direct', 'update_staging', 'defer'];

        return [
            'risk_score'     => (float) max(0.0, min(1.0, $data['risk_score'])),
            'risk_factors'   => array_map('sanitize_text_field', (array) ($data['risk_factors'] ?? [])),
            'recommendation' => in_array($data['recommendation'] ?? '', $rec_valid, true)
                ? $data['recommendation']
                : 'update_staging',
            'summary'        => sanitize_textarea_field($data['summary'] ?? ''),
        ];
    }

    // -------------------------------------------------------------------------
    // Fallback cuando no hay API key o changelog
    // -------------------------------------------------------------------------

    private function fallback_score($slug, $from, $to, $reason = '') {
        // Heurística simple por versión semántica
        $risk_score     = 0.3;
        $risk_factors   = ['scoring_unavailable'];
        $recommendation = 'update_staging';

        if ($from && $to) {
            $from_parts = array_map('intval', explode('.', $from));
            $to_parts   = array_map('intval', explode('.', $to));

            // Major version bump → alto riesgo
            if (($to_parts[0] ?? 0) > ($from_parts[0] ?? 0)) {
                $risk_score     = 0.75;
                $risk_factors   = ['major_version_bump', 'scoring_unavailable'];
                $recommendation = 'defer';
            } elseif (($to_parts[1] ?? 0) > ($from_parts[1] ?? 0)) {
                // Minor version → riesgo moderado
                $risk_score     = 0.45;
                $risk_factors   = ['minor_version_bump', 'scoring_unavailable'];
                $recommendation = 'update_staging';
            }
        }

        return [
            'risk_score'     => $risk_score,
            'risk_factors'   => $risk_factors,
            'recommendation' => $recommendation,
            'summary'        => "Score heurístico (scorer no disponible: {$reason})",
            'cached'         => false,
            'fallback'       => true,
        ];
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public function ajax_score_plugin() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
        }

        $slug = sanitize_key($_POST['slug'] ?? '');
        $from = sanitize_text_field($_POST['from_version'] ?? '');
        $to   = sanitize_text_field($_POST['to_version'] ?? '');

        if (!$slug || !$from || !$to) {
            wp_send_json_error('slug, from_version y to_version son requeridos');
            return;
        }

        $result = $this->score_update($slug, $from, $to);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }

        wp_send_json_success($result);
    }

    public function ajax_fleet_summary() {
        check_ajax_referer('rphub_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes', 403);
        }

        $sites  = RPHUB_Database::get_all_sites();
        $result = [];

        foreach ($sites as $site) {
            if ($site->status !== 'active') {
                continue;
            }
            $assessments = RPHUB_Database::get_site_meta($site->id, 'update_risk_assessments');
            $checked_at  = RPHUB_Database::get_site_meta($site->id, 'update_risk_checked_at');

            if (!$assessments) {
                continue;
            }

            $max_risk = 0.0;
            foreach ((array) $assessments as $a) {
                $max_risk = max($max_risk, $a['risk_score'] ?? 0.0);
            }

            $result[] = [
                'site_id'     => $site->id,
                'site_name'   => $site->name,
                'max_risk'    => $max_risk,
                'assessments' => $assessments,
                'checked_at'  => $checked_at,
            ];
        }

        wp_send_json_success($result);
    }
}
