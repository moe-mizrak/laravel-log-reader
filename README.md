# Laravel Log Reader
Lightweight Laravel package for searching and filtering logs from both file and database sources.

> This package serves as the core log reader for the [Laravel MCP Log](https://github.com/laplace-demon-ai/laravel-mcp-log) (**MCP tool for Laravel log analysing with AI.**), providing its main functionality.

## Installation
You can install the package (that you created with this template) via composer:
  ```bash
  composer require moe-mizrak/laravel-log-reader
  ```

You can publish and run the migrations with:
  ```bash
  php artisan vendor:publish --tag="laravel-log-reader"
  ```

## Usage
You can use the package to read, search, and filter logs from both file and database sources.

If your logs are stored in files (`laravel.log`), in the config file (`laravel-log-reader.php`) set the **driver** to `file` as:
```php
'driver' => env('LOG_READER_DRIVER', LogDriverType::FILE->value), // in .env file LOG_READER_DRIVER=file
```

And set the log file path as:
```php
'path' => env('LOG_FILE_PATH', storage_path('logs/laravel.log')), // in .env file LOG_FILE_PATH=/full/path/to/laravel.log
```

And also you can set a limit, chunk size as:
```php
'chunk_size' => env('LOG_READER_FILE_CHUNK_SIZE', 512 * 1024), // 512KB for file reading
'limit' => env('LOG_READER_FILE_QUERY_LIMIT', 10000),
```

Service provider automatically resolves the correct log reader (`FileLogReader`) and you can use it as:
```php
use MoeMizrak\LaravelLogReader\Facades\LogReader;
use MoeMizrak\LaravelLogReader\Enums\FilterKeyType;

$query = 'User authentication';
$filters = [FilterKeyType::LEVEL->value => 'info'];

$result = LogReader::search($query)->filter($filters)->chunk()->execute();
```

If your logs are stored in database (`log_entries` table), in the config file (`laravel-log-reader.php`) set the **driver** to `db` as:
```php
'driver' => env('LOG_READER_DRIVER', LogDriverType::DB->value), // in .env file LOG_READER_DRIVER=db
```

Set the connection and table name as:
```php
'table' => env('LOG_DB_TABLE', 'log_entries'), // in .env file LOG_DB_TABLE=log_entries
'connection' => env('LOG_DB_CONNECTION'), // in .env file LOG_DB_CONNECTION=mysql
```

And set the database columns mapping and searchable columns as:
```php
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
       
'searchable_columns' => [
    ['name' => LogTableColumnType::MESSAGE->value, 'type' => ColumnType::TEXT->value],
    ['name' => LogTableColumnType::CONTEXT->value, 'type' => ColumnType::JSON->value],
    ['name' => LogTableColumnType::EXTRA->value, 'type' => ColumnType::JSON->value],
],
```

And also you can set a limit, chunk size as:
```php
'limit' => env('LOG_READER_DB_QUERY_LIMIT', 10000), // max number of records to fetch in queries
'chunk_size' => env('LOG_READER_DB_CHUNK_SIZE', 500), // number of records per chunk when chunking is enabled
```

And you can use it as:
```php
use MoeMizrak\LaravelLogReader\Facades\LogReader;
use MoeMizrak\LaravelLogReader\Enums\FilterKeyType;

$query = 'User authentication';
$filters = [FilterKeyType::DATE_FROM->value => '2025-01-01', FilterKeyType::DATE_TO->value => '2025-12-31'];

$result = LogReader::search($query)->filter($filters)->chunk()->execute();
```

> **Note:** You can chain the `search`, `filter`, and `chunk` methods in any order before calling `execute`.
> The `search` method performs a search on searchable fields (like message, context, etc.) based on the provided query (in config we have `searchable_columns` so that it can be customized).

## TODO
> - [ ] Add a `log_insights` migration/table which will be a normalized, summarized, and searchable table.
  > - It unifies different log mechanisms into a single canonical format, enabling faster lookups over large data.
  > - A background task should sync new log data periodically, basically everyday it summarizes the previous day's logs and inserts them into `log_insights`.
  > - Be aware that summarization may lose some details (e.g., exact errors or stack traces).
> - [ ] Add support for cloud log readers (AWS CloudWatch, Azure Monitor, Google Cloud Logging).
> - [ ] Add streaming responses, either as a parameter to search/filter methods or as a new method like `searchStream` using cursors, yields, or `$builder->lazy($chunkSize)`.
> - [ ] Use a cheap/free model to summarize large log files before search/filter (experimental approach).
> - [ ] Refine `LOG_PATTERN` in `FileLogReader` to handle more real-world log formats.
> - [ ] Move `user_id`, `request_id`, and `ip_address` into dedicated columns instead of using the `extra` field.

## Contributing

> **Your contributions are welcome!** If you'd like to improve this project, simply create a pull request with your changes. Your efforts help enhance its functionality and documentation.

> If you find this project useful, please consider ⭐ it to show your support!

## Authors
This project is created and maintained by [Moe Mizrak](https://github.com/moe-mizrak).

## License
Laravel Package Template is an open-sourced software licensed under the **[MIT license](LICENSE)**.