<?php
/**
 * Structured operation logger with bounded option storage.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RAICCOperationLogger
{
    public const OPTION_LOGS = 'raicc_operation_logs';
    public const MAX_ENTRIES = 200;

    /**
     * @param array<string,mixed> $data
     */
    public function log(string $event, array $data = []): void
    {
        $entry = [
            'ts' => gmdate('c'),
            'event' => sanitize_key($event),
            'data' => $this->sanitizeData($data),
        ];

        $stored = get_option(self::OPTION_LOGS, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        $stored[] = $entry;
        if (count($stored) > self::MAX_ENTRIES) {
            $stored = array_slice($stored, -1 * self::MAX_ENTRIES);
        }

        update_option(self::OPTION_LOGS, $stored, false);

        // Keep one-line JSON in debug log for traceability in staging/prod.
        error_log('RAICC_LOG ' . wp_json_encode($entry, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function sanitizeData(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $safeKey = sanitize_key((string) $key);

            if (is_scalar($value) || $value === null) {
                $out[$safeKey] = $value;
                continue;
            }

            if (is_array($value)) {
                $out[$safeKey] = wp_json_encode($value, JSON_UNESCAPED_UNICODE);
                continue;
            }

            $out[$safeKey] = '[unsupported]';
        }

        return $out;
    }
}
