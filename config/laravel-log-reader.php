<?php

declare(strict_types=1);

use MoeMizrak\LaravelLogReader\Enums\LogDriverType;
use MoeMizrak\LaravelLogReader\Enums\LogTableColumnType;

return [
    /*
    |--------------------------------------------------------------------------
    | Log Reader Driver
    |--------------------------------------------------------------------------
    | Supported drivers: 'file' or 'db'
    */
    'driver' => env('LOG_READER_DRIVER', LogDriverType::FILE->value),

    /*
    |--------------------------------------------------------------------------
    | File Log Settings (for 'file' driver)
    |--------------------------------------------------------------------------
    */
    'file' => [
        'path' => env('LOG_FILE_PATH', storage_path('logs/laravel.log')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Log Settings (for 'db' driver)
    |--------------------------------------------------------------------------
    */
    'db' => [
        'table' => env('LOG_DB_TABLE_NAME', 'logs'),
        'connection' => env('LOG_DB_CONNECTION'),

        // todo: maybe add user_id, requiest_id and ip_address columns here too, which are common in many logging setups for database. For now extra column can be used for that info if needed.
        // Column mapping: maps DB columns to LogData properties
        'columns' => [
            LogTableColumnType::ID->value => 'id',
            LogTableColumnType::LEVEL->value => 'level', // e.g. 'ERROR', 'INFO'
            LogTableColumnType::MESSAGE->value => 'message', // main log message
            LogTableColumnType::TIMESTAMP->value => 'created_at', // time of the log entry (e.g. 'created_at' or 'logged_at')
            LogTableColumnType::CHANNEL->value => 'channel', // e.g. 'production', 'local'
            LogTableColumnType::CONTEXT->value => 'context', // additional context info, often JSON e.g. '{"action":"UserLogin"}'
            LogTableColumnType::EXTRA->value => 'extra', // any extra data, often JSON e.g. '{"ip":172.0.0.1, "session_id":"abc", "user_id":123}'
        ],

        // Columns that should be searchable in DB queries
        'searchable_columns' => [
            LogTableColumnType::MESSAGE->value,
            LogTableColumnType::CONTEXT->value,
            LogTableColumnType::EXTRA->value,
        ],
    ],
];
