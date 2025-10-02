<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * LogData DTO — canonical shape for both file and DB log records.
 */
final class LogData extends Data
{
    public function __construct(
        public string|Optional $id,
        public string $levelName,
        public int|Optional $level,
        public string $message,
        public Carbon|string $timestamp,
        public string|Optional $channel,
        public array|string|Optional $context,
        public array|Optional $extra,
    ) {}

    /**
     * Create DTO from a database row
     */
    public static function fromDatabase(object|array $row): self
    {
        $row = (array) $row;

        return new self(
            id: (string) $row['id'],
            levelName: $row['level_name'],
            level: $row['level'],
            message: $row['message'],
            timestamp: Carbon::parse($row['timestamp']),
            channel: $row['extra']['channel'] ?? Optional::create(),
            context: $row['context'] ?? [],
            extra: $row['extra'] ?? []
        );
    }

    /**
     * Create DTO from a parsed file log entry
     */
    public static function fromFile(array $log): self
    {
        return new self(
            id: Optional::create(), // no id in file logs
            levelName: $log['level'], // level of the log e.g. 'error', 'info' etc.
            level: Optional::create(), // no numeric level in file logs
            message: $log['message'], // main log message
            timestamp: Carbon::parse($log['timestamp']), // timestamp of the log entry
            channel: $log['channel'] ?? Optional::create(), // channel may not exist in all log formats
            context: $log['context'] ?? '',
            extra: Optional::create() // no extra in file logs
        );
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id instanceof Optional ? null : $this->id,
            'levelName' => $this->levelName,
            'level' => $this->level instanceof Optional ? null : $this->level,
            'message' => $this->message,
            'timestamp' => $this->timestamp instanceof Carbon ? $this->timestamp->toDateTimeString() : $this->timestamp,
            'channel' => $this->channel instanceof Optional ? null : $this->channel,
            'context' => $this->context instanceof Optional ? null : $this->context,
            'extra' => $this->extra instanceof Optional ? null : $this->extra,
        ];

        // Remove all null values
        return array_filter($data, fn($value) => $value !== null);
    }
}