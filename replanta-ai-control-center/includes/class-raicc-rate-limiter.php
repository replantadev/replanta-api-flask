<?php
/**
 * Lightweight transient-based rate limiter.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RAICCRateLimiter
{
    /**
     * @return array<string,mixed>
     */
    public function check(string $bucket, int $userId, int $limit, int $windowSeconds): array
    {
        $bucket = sanitize_key($bucket);
        $userId = max(0, $userId);
        $limit = max(1, $limit);
        $windowSeconds = max(10, $windowSeconds);

        $key = 'raicc_rl_' . md5($bucket . '|' . $userId);
        $now = time();

        $state = get_transient($key);
        if (!is_array($state)) {
            $state = [
                'count' => 0,
                'reset_at' => $now + $windowSeconds,
            ];
        }

        $count = isset($state['count']) ? (int) $state['count'] : 0;
        $resetAt = isset($state['reset_at']) ? (int) $state['reset_at'] : ($now + $windowSeconds);

        if ($now >= $resetAt) {
            $count = 0;
            $resetAt = $now + $windowSeconds;
        }

        $count++;
        $remaining = max(0, $limit - $count);
        $retryAfter = max(1, $resetAt - $now);

        set_transient($key, [
            'count' => $count,
            'reset_at' => $resetAt,
        ], $retryAfter);

        return [
            'allowed' => $count <= $limit,
            'limit' => $limit,
            'remaining' => $remaining,
            'retry_after' => $retryAfter,
            'bucket' => $bucket,
            'user_id' => $userId,
        ];
    }
}
