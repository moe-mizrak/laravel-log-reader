<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Enums;

/**
 * Enum representing the database log table column types.
 */
enum LogTableColumnType: string
{
    case ID = 'id';
    case LEVEL = 'level';
    case MESSAGE = 'message';
    case TIMESTAMP = 'timestamp';
    case CHANNEL = 'channel';
    case CONTEXT = 'context';
    case EXTRA = 'extra';
}
