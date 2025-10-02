<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array search(string $query)
 * @method static array filter(array $filters = [])
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
