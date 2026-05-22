<?php
/**
 * Native page persistence and blueprint rendering.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RAICCPageService
{
    public const META_BLUEPRINT = '_raicc_blueprint_json';
    public const META_MODE = '_raicc_mode';
    public const META_PROMPT_LAST = '_raicc_prompt_last';
    public const META_CHANGE_ORIGIN = '_raicc_change_origin';

    /**
     * @param array<string,mixed> $blueprint
     * @return array<string,mixed>
     */
    public function createPageFromBlueprint(array $blueprint, string $prompt): array
    {
        $title = isset($blueprint['page']['title']) ? sanitize_text_field((string) $blueprint['page']['title']) : 'Nueva pagina';
        $slug = isset($blueprint['page']['slug']) ? sanitize_title((string) $blueprint['page']['slug']) : sanitize_title($title);

        $postId = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'draft',
            'post_title' => $title,
            'post_name' => $slug,
            'post_content' => $this->renderBlueprintHtml($blueprint),
        ], true);

        if (is_wp_error($postId) || (int) $postId <= 0) {
            return [
                'ok' => false,
                'error' => 'wp_insert_post failed',
            ];
        }

        $postId = (int) $postId;
        update_post_meta($postId, self::META_BLUEPRINT, wp_json_encode($blueprint, JSON_UNESCAPED_UNICODE));
        update_post_meta($postId, self::META_MODE, 'ai');
        update_post_meta($postId, self::META_PROMPT_LAST, $prompt);
        update_post_meta($postId, self::META_CHANGE_ORIGIN, 'ai');

        return [
            'ok' => true,
            'id' => $postId,
            'edit_url' => get_edit_post_link($postId, 'raw'),
            'url' => get_permalink($postId),
            'status' => 'draft',
        ];
    }

    /**
     * @param array<string,mixed> $blueprint
     */
    public function renderBlueprintHtml(array $blueprint): string
    {
        $sections = isset($blueprint['sections']) && is_array($blueprint['sections']) ? $blueprint['sections'] : [];
        $html = "<main id=\"main-content\" class=\"raicc-page\">\n";

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $id = sanitize_html_class((string) ($section['id'] ?? 'section'));
            $type = sanitize_html_class((string) ($section['type'] ?? 'content'));
            $heading = isset($section['heading']) ? sanitize_text_field((string) $section['heading']) : '';
            $body = isset($section['body_markdown']) ? (string) $section['body_markdown'] : '';
            $aria = isset($section['aria_label']) ? sanitize_text_field((string) $section['aria_label']) : '';

            $html .= '<section id="' . esc_attr($id) . '" class="raicc-section raicc-' . esc_attr($type) . '"';
            if ($aria !== '') {
                $html .= ' aria-label="' . esc_attr($aria) . '"';
            }
            $html .= ">\n";

            if ($heading !== '') {
                $tag = $type === 'hero' ? 'h1' : 'h2';
                $html .= '<' . $tag . '>' . esc_html($heading) . '</' . $tag . ">\n";
            }

            if ($body !== '') {
                $html .= wp_kses_post(wpautop($body)) . "\n";
            }

            $html .= "</section>\n";
        }

        $html .= "</main>\n";
        return $html;
    }

    /** @return array<string,mixed> */
    public function setPageStatus(int $postId, string $status): array
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'page') {
            return ['ok' => false, 'error' => 'page not found'];
        }

        if (!in_array($status, ['publish', 'draft'], true)) {
            return ['ok' => false, 'error' => 'invalid status'];
        }

        $updated = wp_update_post([
            'ID' => $postId,
            'post_status' => $status,
        ], true);

        if (is_wp_error($updated)) {
            return ['ok' => false, 'error' => 'wp_update_post failed'];
        }

        return [
            'ok' => true,
            'id' => $postId,
            'status' => $status,
            'url' => get_permalink($postId),
        ];
    }

    /** @return array<int,WP_Post> */
    public function latestPages(int $limit = 30): array
    {
        $limit = max(1, min(200, $limit));
        $posts = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'numberposts' => $limit,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);
        return is_array($posts) ? $posts : [];
    }
}
