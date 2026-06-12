<?php
/**
 * Minimal SVG icon set inspired by Phosphor style.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RAICCIcons
{
    public static function svg(string $name, int $size = 18): string
    {
        $size = max(12, min(32, $size));
        $attrs = 'width="' . $size . '" height="' . $size . '" viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';

        $paths = self::paths($name);
        if ($paths === '') {
            $paths = self::paths('sparkle');
        }

        return '<svg ' . $attrs . '>' . $paths . '</svg>';
    }

    private static function paths(string $name): string
    {
        switch ($name) {
            case 'sparkle':
                return '<path d="M128 32l20 52 52 20-52 20-20 52-20-52-52-20 52-20z"/><path d="M40 200h176"/>';
            case 'rocket':
                return '<path d="M144 32c40 8 72 40 80 80l-52 52-88-88z"/><path d="M84 76L32 128v40h40l52-52"/><path d="M88 168l-24 56 56-24"/>';
            case 'gauge':
                return '<path d="M40 176a88 88 0 0 1 176 0"/><path d="M128 128l40-24"/><path d="M56 176h144"/>';
            case 'check':
                return '<path d="M216 72L104 184l-56-56"/>';
            case 'warning':
                return '<path d="M128 24l104 184H24z"/><path d="M128 96v48"/><circle cx="128" cy="176" r="4" fill="currentColor" stroke="none"/>';
            case 'wand':
                return '<path d="M40 216l88-88"/><path d="M96 40l8 24 24 8-24 8-8 24-8-24-24-8 24-8z"/><path d="M176 120l4 12 12 4-12 4-4 12-4-12-12-4 12-4z"/>';
            case 'settings':
                return '<circle cx="128" cy="128" r="40"/><path d="M128 48v16M128 192v16M48 128H32M224 128h-16M75 75l-12-12M193 193l-12-12M75 181l-12 12M193 63l-12 12"/>';
            default:
                return '';
        }
    }
}
