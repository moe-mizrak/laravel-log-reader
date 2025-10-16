# laravel-log-reader
Lightweight Laravel package for reading, searching, and filtering logs from both file and database sources.

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

And set the connection and table name as:
```php
'table' => env('LOG_DB_TABLE', 'log_entries'), // in .env file LOG_DB_TABLE=log_entries
'connection' => env('LOG_DB_CONNECTION'), // in .env file LOG_DB_CONNECTION=mysql
```

You can set the columns mappings in config file based on your table structure.

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
> - [ ] Add streaming response, maybe as parameter to search/filter methods, maybe as a separate method e.g. searchStream ( which uses cursor/yield/stream, $builder->lazy($chunkSize) etc)
> - [ ] Maybe first a cheap/free model will be used to summarize the large log files and then search/filter on that summary (might not be ideal!)
> - [ ] Modify LOG_PATTERN in FileLogReader, check possible patterns in real world cases so that LOG_PATTERN will cover them
> - [ ] We might end up adding some limits to the file and db log reading (even though it might against what we are achieving here), where when a limit passes certain size, it does not add anymore e.g. while (! feof($handle)) will be something like while (!feof($handle) && $resultCount < $limit); also some logic can be added to db log reader for that.
> - [ ] We might add user_id, request_id and ip_address columns to config and the logic, atm we use extra column for them. But those fields/columns could be added specifically. 
> - [ ] Maybe first get the count/size of the result before doing anything, if it is big then ask for more filter from user, or use streaming etc. So basically first send the count/size request to make sure result is not too big, and then proceed.
> - [ ] Maybe to be able to work with big log data, another approach might be: The log_insights table acts as a normalized, summarized, and semantically searchable index over all log sources — optimized for MCP-style natural language queries.
> This way even though we use different log mechanisms, we normalized them into a single canonical format, so that mcp tool will perform better, also instead of working with big log data, we do have a summarized log table (with index) so that performance will be increased drastically.
> We need a system where periodically it adds up new log data to the table. Will it lack some info in the process of summarizing the logs, so that MCP tool might not find the answer? (Since we store semantic results/summaries, we might miss some asnwers for the prompts like "Show me the exact error messages user 433 got when their login failed.", "What was the stack trace for the 500 errors yesterday?")
> - [ ] We might add some more config fields so that this package can works separated from the main app, e.g. we might add database connection config fields so that it can connect to the log database directly instead of using the main app's database connection.
> - [ ] For now we only have file and db log readers, we might add more log readers in the future e.g. for cloud services like AWS CloudWatch, Azure Monitor, Google Cloud Logging etc.

## Contributing

> **Your contributions are welcome!** If you'd like to improve this project, simply create a pull request with your changes. Your efforts help enhance its functionality and documentation.

> If you find this project useful, please consider ⭐ it to show your support!

## Authors
This project is created and maintained by [Moe Mizrak](https://github.com/moe-mizrak).

## License
Laravel Package Template is an open-sourced software licensed under the **[MIT license](LICENSE)**.