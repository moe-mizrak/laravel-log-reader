<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Enums;

/**
 * Enum representing the types of log drivers.
 */
enum LogDriverType: string
{
    case FILE = 'file';
    case DB = 'db';
}
