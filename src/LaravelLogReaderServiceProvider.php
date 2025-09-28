<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader;

use Illuminate\Support\ServiceProvider;

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
}
