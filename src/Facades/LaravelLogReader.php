<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \MoeMizrak\LaravelLogReader\LaravelLogReader
 */
final class LaravelLogReader extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \MoeMizrak\LaravelLogReader\LaravelLogReader::class;
    }
}