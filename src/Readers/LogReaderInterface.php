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
     * @return $this
     */
    public function search(string $query): static;

    /**
     * Filter logs based on filter criteria.
     *
     * @return $this
     */
    public function filter(array $filters = []): static;

    /**
     * Enable chunked reading of logs.
     *
     * @return $this
     */
    public function chunk(?int $chunkSize = null): static;
}
