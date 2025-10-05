<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Enums;

/**
 * Enum representing the types of filter keys.
 */
enum FilterKeyType: string
{
    case LEVEL = 'level';
    case DATE_FROM = 'date_from';
    case DATE_TO = 'date_to';
    case CHANNEL = 'channel';
}
