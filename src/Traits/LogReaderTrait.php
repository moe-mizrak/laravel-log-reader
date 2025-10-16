<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Traits;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use MoeMizrak\LaravelLogReader\Data\LogData;
use MoeMizrak\LaravelLogReader\Enums\FilterKeyType;
use MoeMizrak\LaravelLogReader\Enums\LogTableColumnType;
use Spatie\LaravelData\Optional;

/**
 * Provides common functionalities for log readers, including parsing log files,
 * applying search and filter callbacks, and converting raw log entries to LogData DTOs.
 */
trait LogReaderTrait
{
    private const string LOG_PATTERN = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)/';

    /**
     * Apply the search and filter callbacks to the given logs array with proper order.
     */
    protected function applyCallbacks(array $logs): array
    {
        // First we call filter callback, because filtering typically reduces the dataset.
        if (is_callable($this->filterCallback)) {
            $logs = ($this->filterCallback)($logs);
        }

        // Then we call search callback.
        if (is_callable($this->searchCallback)) {
            $logs = ($this->searchCallback)($logs);
        }

        return $logs;
    }

    /**
     * Convert raw log entries to LogData DTOs by mapping each entry.
     */
    protected function convertRawLogToLogData(array $logs): array
    {
        return array_map(fn(array $row) => LogData::fromFile($row), $logs);
    }

    /**
     * Check if a log entry matches a given filter key-value pair.
     * Supports specific keys like 'level', 'date_from', 'date_to', and 'channel'.
     * For other keys, it checks if they match properties or 'extra' fields in the LogData DTO.
     */
    protected function matchesFilter(LogData $log, string $key, mixed $value): bool
    {
        return match ($key) {
            FilterKeyType::LEVEL->value => mb_strtolower($log->level) === mb_strtolower((string) $value),
            FilterKeyType::DATE_FROM->value => $this->toCarbon($log->timestamp) >= $this->toCarbon($value),
            FilterKeyType::DATE_TO->value => $this->toCarbon($log->timestamp) <= $this->toCarbon($value),
            FilterKeyType::CHANNEL->value => ! ($log->channel instanceof Optional)
                && mb_strtolower($log->channel) === mb_strtolower((string) $value),
            default => $this->matchesProperty($log, $key, $value),
        };
    }

    /**
     * Parse the log file and return an array of LogData DTOs.
     * Returns an empty array if the file does not exist or is empty.
     */
    protected function parseLogFile(): array
    {
        if (! file_exists($this->filePath)) {
            return [];
        }

        $content = file_get_contents($this->filePath);

        if (empty($content)) {
            return [];
        }

        return $this->convertRawLogToLogData($this->extractLogsFromContent($content));
    }

    /**
     * Extract raw log entries from the log file content using regex pattern matching.
     * Handles multi-line log entries by appending lines that do not match the pattern to the previous entry's context.
     */
    protected function extractLogsFromContent(string $content): array
    {
        $lines = explode(PHP_EOL, $content);
        $logs = [];
        $currentLog = null;

        foreach ($lines as $line) {
            // Check if the line matches the log entry pattern
            if (preg_match(self::LOG_PATTERN, $line, $matches)) {
                // If there's an ongoing log entry, finalize and store it
                if ($currentLog !== null) {
                    $logs[] = $this->finalizeLogEntry($currentLog);
                }

                /*
                 * $matches structure:
                 * [0] => Full matched line
                 * [1] => Timestamp (e.g. '2023-10-05 14:23:45')
                 * [2] => Channel (e.g. 'production')
                 * [3] => Level Name (e.g. 'error', 'info')
                 * [4] => Message (e.g. 'An error occurred')
                 * [5] => Context (if any, may be empty)
                 */
                $currentLog = [
                    LogTableColumnType::TIMESTAMP->value => $matches[1],
                    LogTableColumnType::CHANNEL->value => $matches[2],
                    LogTableColumnType::LEVEL->value => strtoupper($matches[3]),
                    LogTableColumnType::MESSAGE->value => $matches[4],
                    LogTableColumnType::CONTEXT->value => '',
                ];
            } elseif ($currentLog !== null && ! empty(trim($line))) {
                $currentLog[LogTableColumnType::CONTEXT->value] .= $line . PHP_EOL;
            }
        }

        // Finalize the last log entry if exists
        if ($currentLog !== null) {
            $logs[] = $this->finalizeLogEntry($currentLog);
        }

        // Reverse to have the most recent logs first
        return array_reverse($logs);
    }

    /**
     * Executes the query builder and returns LogData array.
     * Optionally processes results in chunks for memory efficiency.
     *
     * @return array<LogData>
     */
    protected function executeQuery(Builder $builder, bool $chunk = false): array
    {
        if ($chunk) {
            $chunkSize = (int) config('laravel-log-reader.db.chunk_size', 500);
            $results = [];

            $builder->chunk($chunkSize, function ($chunk) use (&$results) {
                $results = array_merge($results, $chunk->all());
            });

            return $this->convertRowsToLogData($results);
        }

        return $this->convertRowsToLogData($builder->get()->all());
    }

    protected function applyCustomFilter(Builder $builder, string $key, mixed $value): void
    {
        $column = $this->getColumn($key);

        if ($this->columnExists($column)) {
            $builder->where($column, $value);
        }
    }

    /**
     * Check if a LogData property or an 'extra' field matches the given key-value pair.
     * If the property exists on the LogData object and is not Optional, compare directly.
     * Otherwise, check if 'extra' is an array and contains the key with the matching value.
     */
    private function matchesProperty(LogData $log, string $key, mixed $value): bool
    {
        // Check direct property
        if (property_exists($log, $key) && ! ($log->$key instanceof Optional)) {
            return $this->compareValues($log->$key, $value);
        }

        // Check extra array
        if (is_array($log->extra ?? null) && array_key_exists($key, $log->extra)) {
            return $this->compareValues($log->extra[$key], $value);
        }

        return false;
    }

    /**
     * Compare two values with case-insensitive string comparison.
     */
    private function compareValues(mixed $logValue, mixed $filterValue): bool
    {
        // If both are strings, compare case-insensitively
        if (is_string($logValue) && is_string($filterValue)) {
            return mb_strtolower($logValue) === mb_strtolower($filterValue);
        }

        // Otherwise, strict comparison
        return $logValue === $filterValue;
    }

    /**
     * Finalize a log entry by trimming context and preparing it for conversion.
     * e.g. removing trailing new lines from context etc.
     */
    private function finalizeLogEntry(array $log): array
    {
        $log[LogTableColumnType::CONTEXT->value] = rtrim($log[LogTableColumnType::CONTEXT->value]);

        return $log;
    }

    private function toCarbon(Carbon|string|DateTimeInterface $value): Carbon
    {
        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }

    private function columnExists(string $column): bool
    {
        /** @var Connection $connection */
        $connection = $this->getQueryBuilder()->getConnection();

        return $connection->getSchemaBuilder()->hasColumn($this->table, $column);
    }

    private function getQueryBuilder(): Builder
    {
        return DB::connection($this->connection)->table($this->table);
    }

    /**
     * Converts database rows to an array of LogData DTOs.
     */
    private function convertRowsToLogData(array $rows): array
    {
        return array_map(function (object|array $row) {
            $data = (array) $row;

            return LogData::fromDatabase([
                'id' => Arr::get($data, $this->getColumn('id')),
                'level' => mb_strtolower(Arr::get($data, $this->getColumn(LogTableColumnType::LEVEL->value), '')),
                'message' => Arr::get($data, $this->getColumn(LogTableColumnType::MESSAGE->value), ''),
                'timestamp' => Arr::get($data, $this->getColumn(LogTableColumnType::TIMESTAMP->value), now()),
                'channel' => Arr::get($data, $this->getColumn(LogTableColumnType::CHANNEL->value)),
                'context' => Arr::get($data, $this->getColumn(LogTableColumnType::CONTEXT->value), ''),
                'extra' => $this->getExtraData($data),
            ]);
        }, $rows);
    }

    /**
     * Extracts and decodes the 'extra' field from the log data.
     * Handles cases where 'extra' is stored as a JSON string or an array.
     */
    private function getExtraData(array $data): array
    {
        $extraColumn = $this->getColumn(LogTableColumnType::EXTRA->value);

        $extra = Arr::get($data, $extraColumn, []);

        if (! isset($extra)) {
            return [];
        }

        if (is_string($extra)) {
            $decoded = json_decode($extra, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($extra) ? $extra : [];
    }

    private function getColumn(string $key): string
    {
        return $this->columns[$key] ?? $key;
    }
}
