<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Data;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use MoeMizrak\LaravelLogReader\Enums\LogTableColumnType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * LogData DTO — canonical shape for both file and DB log records.
 */
final class LogData extends Data
{
    public function __construct(
        public string|Optional $id,
        public string $level,
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
            id: (string) Arr::get($row, 'id'),
            level: Arr::get($row, 'level'),
            message: Arr::get($row, 'message'),
            timestamp: Carbon::parse(Arr::get($row, 'timestamp')),
            channel: Arr::get($row, 'channel') ?? Optional::create(),
            context: Arr::get($row, 'context', []),
            extra: Arr::get($row, 'extra', [])
        );
    }

    /**
     * Create DTO from a parsed file log entry
     */
    public static function fromFile(array $log): self
    {
        return new self(
            id: Optional::create(), // no id in file logs
            level: Arr::get($log, LogTableColumnType::LEVEL->value), // level of the log e.g. 'error', 'info' etc.
            message: Arr::get($log, LogTableColumnType::MESSAGE->value), // main log message
            timestamp: Carbon::parse(Arr::get($log, LogTableColumnType::TIMESTAMP->value)), // timestamp of the log entry
            channel: Arr::get($log, LogTableColumnType::CHANNEL->value) ?? Optional::create(), // channel may not exist in all log formats
            context: Arr::get($log, LogTableColumnType::CONTEXT->value, ''),
            extra: Optional::create() // no extra in file logs
        );
    }

    public function toArray(): array
    {
        $data = [
            LogTableColumnType::ID->value => $this->id instanceof Optional ? null : $this->id,
            LogTableColumnType::LEVEL->value => $this->level,
            LogTableColumnType::MESSAGE->value => $this->message,
            LogTableColumnType::TIMESTAMP->value => $this->timestamp instanceof Carbon ? $this->timestamp->toDateTimeString() : $this->timestamp,
            LogTableColumnType::CHANNEL->value => $this->channel instanceof Optional ? null : $this->channel,
            LogTableColumnType::CONTEXT->value => $this->context instanceof Optional ? null : $this->context,
            LogTableColumnType::EXTRA->value => $this->extra instanceof Optional ? null : $this->extra,
        ];

        // Remove all null values
        return array_filter($data, fn($value) => $value !== null);
    }
}