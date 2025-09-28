<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use MoeMizrak\LaravelLogReader\LaravelLogReaderServiceProvider;

/**
 * Base test case for the package.
 */
class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @return string[]
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelLogReaderServiceProvider::class,
        ];
    }
}