<?php

declare(strict_types=1);

use MoeMizrak\LaravelLogReader\Enums\LogDriverType;

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

        // Column mapping: maps DB columns to LogData properties
        'columns' => [
            'id' => 'id',
            'levelName' => 'level_name', // e.g. 'ERROR', 'INFO'
            'level' => 'level', // e.g. 400, 200
            'message' => 'message', // main log message
            'timestamp' => 'created_at', // time of the log entry (e.g. 'created_at' or 'logged_at')
            'channel' => 'channel', // e.g. 'production', 'local'
            'context' => 'context', // additional context info, often JSON e.g. '{"user_id":123}'
            'extra' => 'extra', // any extra data, often JSON e.g. '{"ip":172.0.0.1}'
        ],

        // Columns that should be searchable in DB queries
        'searchable_columns' => ['message', 'context'],
    ],
];
