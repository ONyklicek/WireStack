<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools\Concerns;

/**
 * Shared log-file access for the last-error and read-log-entries tools.
 */
trait ReadsLogFile
{
    protected function logFile(): string
    {
        $configured = config('logging.channels.single.path');

        return is_string($configured) ? $configured : storage_path('logs/laravel.log');
    }

    /**
     * Return the last $limit non-empty lines of the log file.
     *
     * @return array<int, string>
     */
    protected function tailLines(int $limit): array
    {
        $file = $this->logFile();

        if (! is_file($file)) {
            return [];
        }

        $lines = preg_split('/\R/', (string) file_get_contents($file)) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));

        return array_slice($lines, -max(1, $limit));
    }
}
