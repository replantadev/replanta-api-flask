<?php
/**
 * Elementor to semantic theme migration service.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RAICCElementorMigrationService
{
    public const META_MIGRATED = '_raicc_elementor_migrated';
    public const META_BACKUP_CONTENT = '_raicc_elementor_backup_content';
    public const META_BACKUP_DATA = '_raicc_elementor_backup_data';

    public function __construct(private RAICCOperationLogger $logger)
    {
    }

    /** @return array<string,mixed> */
    public function migrateAllPages(bool $disableElementorMeta = false, int $limit = 200): array
    {
        $ids = $this->findElementorPageIds($limit);
        $ok = 0;
        $errors = [];

        foreach ($ids as $id) {
            $res = $this->migratePage($id, $disableElementorMeta);
            if (!empty($res['ok'])) {
                $ok++;
                continue;
            }

            $errors[] = [
                'post_id' => $id,
                'error' => isset($res['error']) ? (string) $res['error'] : 'unknown',
            ];
        }

        return [
            'ok' => true,
            'found' => count($ids),
            'migrated' => $ok,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }

    /** @return array<string,mixed> */
    public function migratePage(int $postId, bool $disableElementorMeta = false): array
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'page') {
            return ['ok' => false, 'error' => 'page not found'];
        }

        $rendered = $this->getElementorRenderedHtml($postId);
        if ($rendered === '') {
            $rendered = (string) $post->post_content;
        }

        if (trim(wp_strip_all_tags($rendered)) === '') {
            return ['ok' => false, 'error' => 'empty elementor html'];
        }

        update_post_meta($postId, self::META_BACKUP_CONTENT, (string) $post->post_content);
        update_post_meta($postId, self::META_BACKUP_DATA, (string) get_post_meta($postId, '_elementor_data', true));

        $semantic = $this->buildSemanticFromHtml($rendered, (string) $post->post_title);

        $updated = wp_update_post([
            'ID' => $postId,
            'post_content' => $semantic,
        ], true);

        if (is_wp_error($updated)) {
            return ['ok' => false, 'error' => 'wp_update_post failed'];
        }

        update_post_meta($postId, self::META_MIGRATED, '1');

        if ($disableElementorMeta) {
            delete_post_meta($postId, '_elementor_data');
            delete_post_meta($postId, '_elementor_edit_mode');
            delete_post_meta($postId, '_elementor_template_type');
        }

        $this->logger->log('elementor_page_migrated', [
            'post_id' => $postId,
            'disable_elementor_meta' => $disableElementorMeta ? 1 : 0,
        ]);

        return ['ok' => true, 'post_id' => $postId];
    }

    /** @return array<string,mixed> */
    public function migrateThemeBuilderTemplatesToThemeMods(): array
    {
        $headerHtml = $this->findThemeBuilderHtml('header');
        $footerHtml = $this->findThemeBuilderHtml('footer');

        if ($headerHtml !== '') {
            set_theme_mod('rlt_header_main_cols', 1);
            set_theme_mod('rlt_header_main_1_module', 'html');
            set_theme_mod('rlt_header_main_1_html', wp_kses_post($headerHtml));
        }

        if ($footerHtml !== '') {
            set_theme_mod('rlt_footer_main_cols', 1);
            set_theme_mod('rlt_footer_main_1_module', 'html');
            set_theme_mod('rlt_footer_main_1_html', wp_kses_post($footerHtml));
        }

        $this->logger->log('elementor_theme_builder_migrated', [
            'header_found' => $headerHtml !== '' ? 1 : 0,
            'footer_found' => $footerHtml !== '' ? 1 : 0,
        ]);

        return [
            'ok' => true,
            'header_found' => $headerHtml !== '',
            'footer_found' => $footerHtml !== '',
        ];
    }

    /** @return array<int,int> */
    private function findElementorPageIds(int $limit): array
    {
        $ids = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
            'numberposts' => max(1, min(1000, $limit)),
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_elementor_edit_mode',
                    'value' => 'builder',
                    'compare' => '=',
                ],
                [
                    'key' => '_elementor_data',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        return array_map('intval', is_array($ids) ? $ids : []);
    }

    private function getElementorRenderedHtml(int $postId): string
    {
        if (!class_exists('Elementor\\Plugin')) {
            return '';
        }

        try {
            $html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($postId, true);
            return is_string($html) ? $html : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function buildSemanticFromHtml(string $html, string $title): string
    {
        $clean = trim($html);
        $hasH1 = stripos($clean, '<h1') !== false;

        $out = "<main id=\"main-content\" class=\"rlt-main rlt-migrated-from-elementor\">\n";
        $out .= "<article class=\"rlt-entry\">\n";

        if (!$hasH1 && $title !== '') {
            $out .= '<h1 class="rlt-entry-title">' . esc_html($title) . "</h1>\n";
        }

        $out .= '<div class="rlt-entry-content">' . wp_kses_post($clean) . "</div>\n";
        $out .= "</article>\n";
        $out .= "</main>\n";

        return $out;
    }

    private function findThemeBuilderHtml(string $templateType): string
    {
        $ids = get_posts([
            'post_type' => 'elementor_library',
            'post_status' => ['publish', 'private'],
            'numberposts' => 1,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => '_elementor_template_type',
                'value' => $templateType,
                'compare' => '=',
            ]],
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        $templateId = is_array($ids) && isset($ids[0]) ? (int) $ids[0] : 0;
        if ($templateId <= 0) {
            return '';
        }

        return $this->getElementorRenderedHtml($templateId);
    }
}
