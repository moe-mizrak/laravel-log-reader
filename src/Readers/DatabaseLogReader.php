<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Readers;

use MoeMizrak\LaravelLogReader\Data\LogData;
use MoeMizrak\LaravelLogReader\Enums\FilterKeyType;
use MoeMizrak\LaravelLogReader\Enums\LogTableColumnType;
use MoeMizrak\LaravelLogReader\Traits\LogReaderTrait;

/**
 * DatabaseLogReader reads logs from a database table, implementing search and filter functionalities.
 */
final class DatabaseLogReader implements LogReaderInterface
{
    use LogReaderTrait;

    private array $columns;

    private array $searchableColumns;

    private bool $chunk = false;

    private ?string $searchQuery = null;

    private array $filters = [];

    public function __construct(
        protected string $table,
        protected ?string $connection = null
    ) {
        $this->columns = config('laravel-log-reader.db.columns', []);
        $this->searchableColumns = config('laravel-log-reader.db.searchable_columns', []);
    }

    /**
     * {@inheritDoc}
     */
    public function search(string $query): static
    {
        $this->searchQuery = $query;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function filter(array $filters = []): static
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function chunk(?int $chunkSize = null): static
    {
        $this->chunk = true;

        return $this;
    }

    /**
     * @return array<LogData>
     */
    public function execute(): array
    {
        $builder = $this->getQueryBuilder();

        // Apply search
        if (! empty($this->searchQuery)) {
            $columns = array_map(fn($col) => $this->getColumn($col), $this->searchableColumns);
            $builder->whereAny($columns, 'like', '%' . $this->searchQuery . '%');
        }

        // Apply filters
        foreach ($this->filters as $key => $value) {
            match ($key) {
                FilterKeyType::LEVEL->value => $builder->where($this->getColumn(LogTableColumnType::LEVEL->value), mb_strtolower((string) $value)),
                FilterKeyType::DATE_FROM->value => $builder->where($this->getColumn(LogTableColumnType::TIMESTAMP->value), '>=', $value),
                FilterKeyType::DATE_TO->value => $builder->where($this->getColumn(LogTableColumnType::TIMESTAMP->value), '<=', $value),
                FilterKeyType::CHANNEL->value => $builder->where($this->getColumn(LogTableColumnType::CHANNEL->value), $value),
                default => $this->applyCustomFilter($builder, $key, $value),
            };
        }

        $builder->orderByDesc($this->getColumn(LogTableColumnType::TIMESTAMP->value));

        return $this->executeQuery($builder, $this->chunk);
    }
}