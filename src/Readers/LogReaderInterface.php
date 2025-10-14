<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Readers;

/**
 * Interface for log readers, defining methods for searching and filtering logs.
 */
interface LogReaderInterface
{
    /**
     * Search logs based on a query string.
     *
     * @return array<LogData>
     */
    public function search(string $query, bool $chunk = false): array;

    /**
     * Filter logs based on filter criteria.
     *
     * @return array<LogData>
     */
    public function filter(array $filters = [], bool $chunk = false): array;
}
