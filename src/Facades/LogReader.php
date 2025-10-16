<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Facades;

use Illuminate\Support\Facades\Facade;
use MoeMizrak\LaravelLogReader\Data\LogData;

/**
 * @method static static search(string $query)
 * @method static static filter(array $filters = [])
 * @method static static chunk(?int $chunkSize = null)
 * @method static array<LogData> execute()
 */
final class LogReader extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'log-reader';
    }
}
