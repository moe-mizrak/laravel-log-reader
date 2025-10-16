<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Readers;

use Closure;
use MoeMizrak\LaravelLogReader\Data\LogData;
use MoeMizrak\LaravelLogReader\Traits\LogReaderTrait;

/**
 * FileLogReader reads logs from a log file, implementing search and filter functionalities.
 */
final class FileLogReader implements LogReaderInterface
{
    use LogReaderTrait;

    private bool $chunk = false;

    private int $chunkSize = 512 * 1024; // Default to 512KB

    private ?Closure $searchCallback = null;

    private ?Closure $filterCallback = null;

    public function __construct(protected string $filePath) {}

    /**
     * {@inheritDoc}
     */
    public function search(string $query): static
    {
        if (empty($query) || ! file_exists($this->filePath)) {
            return $this;
        }

        $searchTerm = mb_strtolower($query);

        $this->searchCallback = function (array $logs) use ($searchTerm): array {
            return array_filter(
                $logs,
                fn(LogData $log) => str_contains(mb_strtolower($log->message ?? ''), $searchTerm) ||
                    str_contains(mb_strtolower($log->context ?? ''), $searchTerm)
            );
        };

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function filter(array $filters = []): static
    {
        if (! file_exists($this->filePath)) {
            return $this;
        }

        $this->filterCallback = function (array $logs) use ($filters): array {
            foreach ($filters as $key => $value) {
                $logs = array_filter($logs, fn(LogData $log) => $this->matchesFilter($log, $key, $value));
            }

            return $logs;
        };

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function chunk(?int $chunkSize = null): static
    {
        $this->chunk = true;
        $this->chunkSize = $chunkSize ?? (int) config('laravel-log-reader.file.chunk_size', 512 * 1024);

        return $this;
    }

    /**
     * @return array<LogData>
     */
    public function execute(): array
    {
        $logs = [];

        if (! $this->chunk) {
            $logs = $this->parseLogFile();

            // Apply search and filter callbacks if set
            $logs = $this->applyCallbacks($logs);

            // If both search and filter were applied, return the search results.
            return array_values($logs);
        }

        $handle = @fopen($this->filePath, 'r');

        if (! $handle) {
            return $logs;
        }

        $results = [];
        $buffer = '';

        while (! feof($handle)) {
            $buffer .= fread($handle, $this->chunkSize);

            if (! feof($handle)) {
                $lastNewLinePos = strrpos($buffer, PHP_EOL);

                if ($lastNewLinePos === false) {
                    continue;
                }

                $contentChunk = substr($buffer, 0, $lastNewLinePos);
                $buffer = substr($buffer, $lastNewLinePos + 1);
            } else {
                $contentChunk = $buffer;
                $buffer = '';
            }

            $logs = $this->convertRawLogToLogData($this->extractLogsFromContent($contentChunk));

            // Apply search and filter callbacks on the current chunk
            $logs = $this->applyCallbacks($logs);

            array_push($results, ...$logs);
        }

        fclose($handle);

        return $results;
    }
}
