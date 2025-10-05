<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader;

use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use MoeMizrak\LaravelLogReader\Enums\LogDriverType;
use MoeMizrak\LaravelLogReader\Readers\DatabaseLogReader;
use MoeMizrak\LaravelLogReader\Readers\FileLogReader;
use MoeMizrak\LaravelLogReader\Readers\LogReaderInterface;

final class LaravelLogReaderServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->configure();

        // Bind the LogReaderInterface to the appropriate implementation based on the config driver value
        $this->app->bind(LogReaderInterface::class, function () {
            $driver = config('laravel-log-reader.driver');

            return match ($driver) {
                LogDriverType::DB->value => $this->initializeDatabaseLogReader(),
                LogDriverType::FILE->value => $this->initializeFileLogReader(),
                default => throw new InvalidArgumentException("Invalid log driver: {$driver}"),
            };
        });

        // Bind the log reader facade to the appropriate implementation based on the config driver value
        $this->app->singleton('log-reader', function (Container $app) {
            $driver = config('laravel-log-reader.driver');

            return match ($driver) {
                LogDriverType::DB->value => $app->make(DatabaseLogReader::class),
                LogDriverType::FILE->value => $app->make(FileLogReader::class),
                default => throw new InvalidArgumentException("Invalid log driver: {$driver}"),
            };
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['laravel-log-reader'];
    }

    /**
     * Setup the configuration.
     */
    protected function configure(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laravel-log-reader.php', 'laravel-log-reader'
        );
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/laravel-log-reader.php' => config_path('laravel-log-reader.php'),
            ], 'laravel-log-reader');
        }
    }

    /**
     * Initialize the DatabaseLogReader with config values.
     */
    private function initializeDatabaseLogReader(): DatabaseLogReader
    {
        $table = config('laravel-log-reader.db.table');
        $connection = config('laravel-log-reader.db.connection');

        return new DatabaseLogReader(
            $table,
            $connection
        );
    }

    /**
     * Initialize the FileLogReader with config values.
     */
    private function initializeFileLogReader(): FileLogReader
    {
        $filePath = config('laravel-log-reader.file.path');

        return new FileLogReader($filePath);
    }
}
