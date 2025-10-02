<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Readers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use MoeMizrak\LaravelLogReader\Data\LogData;

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
        $this->searchableColumns = config('laravel-log-reader.db.searchable_columns', ['message', 'context']);
    }

    public function search(string $query): array
    {
        if (empty($query)) {
            return [];
        }

        $results = $this->getQueryBuilder()
            ->where(function (Builder $q) use ($query) {
                $param = '%' . mb_strtolower($query) . '%';

                foreach ($this->searchableColumns as $index => $col) {
                    $column = $this->getColumn($col);

                    if ($index === 0) {
                        $q->whereRaw("LOWER({$column}) LIKE ?", [$param]);
                    } else {
                        $q->orWhereRaw("LOWER({$column}) LIKE ?", [$param]);
                    }
                }
            })
            ->orderByDesc($this->getColumn('timestamp'))
            ->get();

        return $this->convertToLogData($results->all());
    }

    public function filter(array $filters = []): array
    {
        $builder = $this->getQueryBuilder();

        if (empty($filters)) {
            return $this->convertToLogData(
                $builder->orderByDesc($this->getColumn('timestamp'))->get()->all()
            );
        }

        foreach ($filters as $key => $value) {
            // Apply specific filters based on known keys
            match ($key) {
                'level' => $builder->where($this->getColumn('levelName'), mb_strtoupper((string) $value)),
                'date_from' => $builder->where($this->getColumn('timestamp'), '>=', $value),
                'date_to' => $builder->where($this->getColumn('timestamp'), '<=', $value),
                'channel' => $builder->where($this->getColumn('channel'), $value),
                default => $this->applyCustomFilter($builder, $key, $value),
            };
        }

        // Return results ordered by creation date descending
        return $this->convertToLogData(
            $builder->orderByDesc($this->getColumn('timestamp'))->get()->all()
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
        return $this->getQueryBuilder()
            ->getConnection()
            ->getSchemaBuilder()
            ->hasColumn($this->table, $column);
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
                'level_name' => mb_strtoupper(Arr::get($data, $this->getColumn('levelName'), '')),
                'level' => $this->getLevelCode(Arr::get($data, $this->getColumn('levelName'), '')),
                'message' => Arr::get($data, $this->getColumn('message'), ''),
                'timestamp' => Arr::get($data, $this->getColumn('timestamp'), now()),
                'channel' => Arr::get($data, $this->getColumn('channel')),
                'context' => Arr::get($data, $this->getColumn('context'), ''),
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
        $extraColumn = $this->getColumn('extra');

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

    /**
     * Gets the numerical Monolog log level value for a given level string.
     */
    protected function getLevelCode(string $level): int
    {
        // todo maybe make them enums
        return match (mb_strtoupper($level)) {
            'DEBUG' => 100,
            'INFO' => 200,
            'NOTICE' => 250,
            'WARNING' => 300,
            'ERROR' => 400,
            'CRITICAL' => 500,
            'ALERT' => 550,
            'EMERGENCY' => 600,
            default => 0,
        };
    }

    protected function getColumn(string $key): string
    {
        return $this->columns[$key] ?? $key;
    }
}
