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

## Contributing

> **Your contributions are welcome!** If you'd like to improve this project, simply create a pull request with your changes. Your efforts help enhance its functionality and documentation.

> If you find this project useful, please consider ⭐ it to show your support!

## Authors
This project is created and maintained by [Moe Mizrak](https://github.com/moe-mizrak).

## License
Laravel Package Template is an open-sourced software licensed under the **[MIT license](LICENSE)**.