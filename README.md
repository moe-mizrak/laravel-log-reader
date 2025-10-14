# laravel-log-reader
Lightweight Laravel package for reading, searching, and filtering logs from both file and database sources.

# 🚧 Under Construction 🚧

## Installation
You can install the package (that you created with this template) via composer:
  ```bash
  composer require moe-mizrak/laravel-log-reader
  ```

You can publish and run the migrations with:
  ```bash
  php artisan vendor:publish --tag="laravel-log-reader"
  ```

## TODO
> - [ ] Add streaming response, maybe as parameter to search/filter methods, maybe as a separate method e.g. searchStream ( which uses cursor/yield/stream, $builder->lazy($chunkSize) etc)
> - [ ] Maybe first a cheap/free model will be used to summarize the large log files and then search/filter on that summary (might not be ideal)
> - [ ] Modify LOG_PATTERN in FileLogReader, check ppssible patterns in real world cases so that LOG_PATTERN will cover them
> - [ ] We might end up adding some limits to the file and db log reading (even though it might against what we are achieving here), where when a limit passes cetain size, it does not add anymore e.g. while (! feof($handle)) will be someting like while (!feof($handle) && $resultCount < $limit); also some logic can be added to db log reader for that.
> - [ ] we might add user_id, requiest_id and ip_address columns to config and the logic, atm we use extra column for them. But those fields/columns could be added specifically. 
> - [ ] Maybe first get the count/size of the result before doing anything, if it is big then ask for more filter from user, or use streaming etc. So basically first send the count/size request to make sure result is not too big, and then proceed.
> - [ ] Maybe to be able to work with big log data, another approach might be: The log_insights table acts as a normalized, summarized, and semantically searchable index over all log sources — optimized for MCP-style natural language queries. This way even thoug we use different log mechanisms, we normalized them into a single canonical format, so that mcp tool will perform better, also instead of working with big log data, we do have a summarized log table (with index) so that performance will be increased drastically. We need a system where periodically it adds up new log data to the table. Will it lack some info in the process of summarizing the logs, so that MCP tool might not find the answer? (Since we store semantic results/summaries, we might miss some asnwers for the prompts like "Show me the exact error messages user 433 got when their login failed.", "What was the stack trace for the 500 errors yesterday?")

## Contributing

> **Your contributions are welcome!** If you'd like to improve this project, simply create a pull request with your changes. Your efforts help enhance its functionality and documentation.

> If you find this project useful, please consider ⭐ it to show your support!

## Authors
This project is created and maintained by [Moe Mizrak](https://github.com/moe-mizrak).

## License
Laravel Package Template is an open-sourced software licensed under the **[MIT license](LICENSE)**.