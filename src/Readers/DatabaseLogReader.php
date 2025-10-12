<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Readers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use MoeMizrak\LaravelLogReader\Data\LogData;
use MoeMizrak\LaravelLogReader\Enums\FilterKeyType;
use MoeMizrak\LaravelLogReader\Enums\LogTableColumnType;

/**
 * DatabaseLogReader reads logs from a database table, implementing search and filter functionalities.
 */
final readonly class DatabaseLogReader implements LogReaderInterface
{
    private array $columns;

    private array $searchableColumns;

    public function __construct(
        protected string $table,
        protected ?string $connection = null
    ) {
        $this->columns = config('laravel-log-reader.db.columns', []);
        $this->searchableColumns = config('laravel-log-reader.db.searchable_columns', []);
    }

    public function search(string $query, bool $chunk = false): array
    {
        if (empty($query)) {
            return [];
        }

        $columns = array_map(fn($col) => $this->getColumn($col), $this->searchableColumns);

        $builder = $this->getQueryBuilder()
            ->whereAny($columns, 'like', '%' . $query . '%')
            ->orderByDesc($this->getColumn(LogTableColumnType::TIMESTAMP->value));

        if ($chunk) {
            $chunkSize = (int) config('laravel-log-reader.db.chunk_size', 500);
            $results = [];

            $builder->chunk($chunkSize, function ($chunk) use (&$results) {
                $results = array_merge($results, $chunk->all());
            });

            return $this->convertToLogData($results);
        }

        return $this->convertToLogData($builder->get()->all());
    }

    public function filter(array $filters = []): array
    {
        $builder = $this->getQueryBuilder();

        if (empty($filters)) {
            return $this->convertToLogData(
                $builder->orderByDesc($this->getColumn(LogTableColumnType::TIMESTAMP->value))->get()->all()
            );
        }

        foreach ($filters as $key => $value) {
            // Apply specific filters based on known keys
            match ($key) {
                FilterKeyType::LEVEL->value => $builder->where($this->getColumn(LogTableColumnType::LEVEL->value), mb_strtolower((string) $value)),
                FilterKeyType::DATE_FROM->value => $builder->where($this->getColumn(LogTableColumnType::TIMESTAMP->value), '>=', $value),
                FilterKeyType::DATE_TO->value => $builder->where($this->getColumn(LogTableColumnType::TIMESTAMP->value), '<=', $value),
                FilterKeyType::CHANNEL->value => $builder->where($this->getColumn(LogTableColumnType::CHANNEL->value), $value),
                default => $this->applyCustomFilter($builder, $key, $value),
            };
        }

        // Return results ordered by creation date descending
        return $this->convertToLogData(
            $builder->orderByDesc($this->getColumn(LogTableColumnType::TIMESTAMP->value))->get()->all()
        );
    }

    protected function applyCustomFilter(Builder $builder, string $key, mixed $value): void
    {
        $column = $this->getColumn($key);

        if ($this->columnExists($column)) {
            $builder->where($column, $value);
        }
    }

    protected function columnExists(string $column): bool
    {
        /** @var \Illuminate\Database\Connection $connection */
        $connection = $this->getQueryBuilder()->getConnection();

        return $connection->getSchemaBuilder()->hasColumn($this->table, $column);
    }

    protected function getQueryBuilder(): Builder
    {
        return DB::connection($this->connection)->table($this->table);
    }

    /**
     * Converts database rows to an array of LogData DTOs.
     */
    protected function convertToLogData(array $rows): array
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
    protected function getExtraData(array $data): array
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

    protected function getColumn(string $key): string
    {
        return $this->columns[$key] ?? $key;
    }
}
