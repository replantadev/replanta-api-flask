<?php
/**
 * Minimal blueprint schema validator.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RAICCBlueprintValidator
{
    /**
     * @param array<string,mixed> $blueprint
     * @return array<string,mixed>
     */
    public function validate(array $blueprint): array
    {
        $errors = [];

        $this->requireNonEmptyString($blueprint, 'version', 'version is required', $errors);
        $this->requireNonEmptyString($blueprint, 'lang', 'lang is required', $errors);

        if (!isset($blueprint['page']) || !is_array($blueprint['page'])) {
            $errors[] = 'page object is required';
        } else {
            $this->requireNonEmptyString($blueprint['page'], 'title', 'page.title is required', $errors);
            $this->requireNonEmptyString($blueprint['page'], 'slug', 'page.slug is required', $errors);
        }

        $sections = $blueprint['sections'] ?? null;
        if (!is_array($sections) || $sections === []) {
            $errors[] = 'sections must be a non-empty array';
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param array<int,string> $errors
     */
    private function requireNonEmptyString(array $source, string $key, string $message, array &$errors): void
    {
        $value = isset($source[$key]) ? (string) $source[$key] : '';
        if ($value === '') {
            $errors[] = $message;
        }
    }
}
