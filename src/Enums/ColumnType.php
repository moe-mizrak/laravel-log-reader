<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Enums;

/**
 * Enum representing the types of columns in the log table.
 */
enum ColumnType: string
{
    case TEXT = 'text';
    case JSON = 'json';
}
