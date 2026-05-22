<?php
/**
 * Publish-time quality gates (a11y + semantic + seo baseline).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RAICCPublishGateValidator
{
    /** @return array<string,mixed> */
    public function evaluatePage(int $postId): array
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'page') {
            return [
                'ok' => false,
                'blockers' => ['page not found'],
                'warnings' => [],
                'score' => 0,
            ];
        }

        $title = (string) $post->post_title;
        $content = (string) $post->post_content;

        $blockers = [];
        $warnings = [];
        $score = 100;

        if ($title === '') {
            $blockers[] = 'missing page title';
            $score -= 25;
        }

        if (mb_strlen(trim(wp_strip_all_tags($content))) < 120) {
            $warnings[] = 'very short content';
            $score -= 10;
        }

        if (stripos($content, '<main') === false) {
            $blockers[] = 'missing main landmark';
            $score -= 30;
        }

        if (stripos($content, '<section') === false) {
            $warnings[] = 'no section landmarks found';
            $score -= 8;
        }

        preg_match_all('/<h1\b[^>]*>/i', $content, $h1Matches);
        $h1Count = isset($h1Matches[0]) ? count($h1Matches[0]) : 0;
        if ($h1Count !== 1) {
            $blockers[] = 'page must contain exactly one h1';
            $score -= 20;
        }

        preg_match_all('/<img\b[^>]*>/i', $content, $imgMatches);
        $images = isset($imgMatches[0]) && is_array($imgMatches[0]) ? $imgMatches[0] : [];
        foreach ($images as $imgTag) {
            if (!preg_match('/\salt\s*=\s*(["\']).*?\1/i', $imgTag)) {
                $blockers[] = 'image without alt attribute';
                $score -= 15;
                break;
            }
        }

        $seoTitleLen = mb_strlen($title);
        if ($seoTitleLen < 20 || $seoTitleLen > 65) {
            $warnings[] = 'title length outside recommended seo range (20-65)';
            $score -= 5;
        }

        $ok = $blockers === [];

        return [
            'ok' => $ok,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'score' => max(0, $score),
        ];
    }
}
