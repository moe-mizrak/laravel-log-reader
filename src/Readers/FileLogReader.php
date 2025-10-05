<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Readers;

use Carbon\Carbon;
use DateTimeInterface;
use MoeMizrak\LaravelLogReader\Data\LogData;
use MoeMizrak\LaravelLogReader\Enums\FilterKeyType;
use MoeMizrak\LaravelLogReader\Enums\LogTableColumnType;
use Spatie\LaravelData\Optional;

/**
 * FileLogReader reads logs from a log file, implementing search and filter functionalities.
 */
final readonly class FileLogReader implements LogReaderInterface
{
    // todo: maybe fist a cheap/free model will be used to summarize the large log files and then search/filter on that summary
    private const string LOG_PATTERN = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)/';

    public function __construct(protected string $filePath) {}

    public function search(string $query): array
    {
        if (empty($query)) {
            return [];
        }

        $logs = $this->parseLogFile();
        $searchTerm = mb_strtolower($query);

        // Filter logs where the message or context contains the search term (case-insensitive)
        return array_values(array_filter(
            $logs,
            fn(LogData $log) => str_contains(mb_strtolower($log->message ?? ''), $searchTerm)
                || str_contains(mb_strtolower($log->context ?? ''), $searchTerm)
        ));
    }

    public function filter(array $filters = []): array
    {
        $logs = $this->parseLogFile();

        if (empty($filters)) {
            return $logs;
        }

        foreach ($filters as $key => $value) {
            $logs = array_filter($logs, fn(LogData $log) => $this->matchesFilter($log, $key, $value));
        }

        return array_values($logs);
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
     * Check if a LogData property or an 'extra' field matches the given key-value pair.
     * If the property exists on the LogData object and is not Optional, compare directly.
     * Otherwise, check if 'extra' is an array and contains the key with the matching value.
     */
    protected function matchesProperty(LogData $log, string $key, mixed $value): bool
    {
        if (property_exists($log, $key) && ! ($log->$key instanceof Optional)) {
            return $value === $log->$key;
        }

        return is_array($log->extra ?? null) && ($log->extra[$key] ?? null) === $value;
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

        return $this->convertToLogData($this->extractLogsFromContent($content));
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
     * Finalize a log entry by trimming context and preparing it for conversion.
     * e.g. removing trailing new lines from context etc.
     */
    protected function finalizeLogEntry(array $log): array
    {
        $log[LogTableColumnType::CONTEXT->value] = rtrim($log[LogTableColumnType::CONTEXT->value]);

        return $log;
    }

    /**
     * Convert raw log entries to LogData DTOs by mapping each entry.
     */
    protected function convertToLogData(array $logs): array
    {
        return array_map(fn(array $row) => LogData::fromFile($row), $logs);
    }

    protected function toCarbon(Carbon|string|DateTimeInterface $value): Carbon
    {
        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }
}
