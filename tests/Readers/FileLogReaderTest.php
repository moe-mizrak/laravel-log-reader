<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Tests\Readers;

use MoeMizrak\LaravelLogReader\Enums\FilterKeyType;
use MoeMizrak\LaravelLogReader\Enums\LogDriverType;
use MoeMizrak\LaravelLogReader\Readers\FileLogReader;
use MoeMizrak\LaravelLogReader\Readers\LogReaderInterface;
use MoeMizrak\LaravelLogReader\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(FileLogReader::class)]
final class FileLogReaderTest extends TestCase
{
    private string $logFile;
    private FileLogReader $fileReader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logFile = tempnam(sys_get_temp_dir(), 'log_');
        $this->createLogFile();

        config([
            'laravel-log-reader.driver' => LogDriverType::FILE->value,
            'laravel-log-reader.file.path' => $this->logFile,
        ]);

        $this->fileReader = app(LogReaderInterface::class);
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_returns_empty_array_if_file_does_not_exist(): void
    {
        /* EXECUTE */
        $reader = new FileLogReader('/non/existing/path.log');

        /* ASSERT */
        $this->assertSame([], $reader->search('anything'));
        $this->assertSame([], $reader->filter([FilterKeyType::LEVEL->value => 'info']));
    }

    #[Test]
    public function it_returns_empty_array_for_empty_search_query(): void
    {
        /* EXECUTE */
        $result = $this->fileReader->search('');

        /* ASSERT */
        $this->assertSame([], $result);
    }

    #[Test]
    public function it_returns_all_logs_when_no_filters_provided(): void
    {
        /* EXECUTE */
        $result = $this->fileReader->filter([]);

        /* ASSERT */
        $this->assertCount(4, $result);
    }

    #[Test]
    public function it_parses_logs_correctly(): void
    {
        /* EXECUTE */
        $logs = $this->invokeParse($this->fileReader);

        /* ASSERT */
        $this->assertCount(4, $logs);
        $this->assertSame('WARNING', $logs[0]->level);
        $this->assertSame('production', $logs[0]->channel);
        $this->assertStringContainsString('Queue job failed', $logs[0]->message);
        $this->assertSame('DEBUG', $logs[1]->level);
        $this->assertSame('ERROR', $logs[2]->level);
        $this->assertNotEmpty($logs[2]->context);
        $this->assertSame('INFO', $logs[3]->level);
    }

    #[Test]
    public function it_handles_multiline_stack_traces(): void
    {
        /* EXECUTE */
        $logs = $this->invokeParse($this->fileReader);
        $errorLog = $this->findLogByLevel($logs, 'ERROR');

        /* ASSERT */
        $this->assertNotNull($errorLog);
        $this->assertStringContainsString('Call to undefined method', $errorLog->message);
        $this->assertStringContainsString('Stack trace:', $errorLog->context);
        $this->assertStringContainsString('#0 /var/www/html/app/Http/Controllers', $errorLog->context);
    }

    #[Test]
    public function it_searches_logs_by_message_and_context(): void
    {
        /* EXECUTE */
        $byMessage = $this->fileReader->search('undefined method');
        $byContext = $this->fileReader->search('Stack trace');

        /* ASSERT */
        $this->assertCount(1, $byMessage);
        $this->assertSame('ERROR', $byMessage[0]->level);
        $this->assertCount(1, $byContext);
        $this->assertNotEmpty($byContext[0]->context);
    }

    #[Test]
    public function it_searches_case_insensitively(): void
    {
        /* EXECUTE */
        $firstResult = $this->fileReader->search('user authentication');
        $secondResult = $this->fileReader->search('USER AUTHENTICATION');

        /* ASSERT */
        $this->assertCount(1, $firstResult);
        $this->assertCount(1, $secondResult);
    }

    #[Test]
    public function it_filters_by_level(): void
    {
        /* EXECUTE */
        $firstResult = $this->fileReader->filter([FilterKeyType::LEVEL->value => 'info']);
        $secondResult = $this->fileReader->filter([FilterKeyType::LEVEL->value => 'debug']);
        $thirdResult = $this->fileReader->filter([FilterKeyType::LEVEL->value => 'error']);
        $fourthResult = $this->fileReader->filter([FilterKeyType::LEVEL->value => 'warning']);
        $fifthResult = $this->fileReader->filter([FilterKeyType::LEVEL->value => 'INFO']);

        /* ASSERT */
        $this->assertCount(1, $firstResult);
        $this->assertCount(1, $secondResult);
        $this->assertCount(1, $thirdResult);
        $this->assertCount(1, $fourthResult);
        $this->assertCount(1, $fifthResult);
    }

    #[Test]
    public function it_filters_by_channel(): void
    {
        /* EXECUTE */
        $firstResult = $this->fileReader->filter([FilterKeyType::CHANNEL->value => 'local']);
        $secondResult = $this->fileReader->filter([FilterKeyType::CHANNEL->value => 'LOCAL']);
        $thirdResult = $this->fileReader->filter([FilterKeyType::CHANNEL->value => 'production']);

        /* ASSERT */
        $this->assertCount(3, $firstResult);
        $this->assertCount(3, $secondResult);
        $this->assertCount(1, $thirdResult);
    }

    #[Test]
    public function it_filters_by_date_range(): void
    {
        /* EXECUTE */
        $firstResult = $this->fileReader->filter([FilterKeyType::DATE_FROM->value => '2025-09-28 12:05:00']);
        $secondResult = $this->fileReader->filter([FilterKeyType::DATE_TO->value => '2025-09-28 12:10:00']);
        $thirdResult = $this->fileReader->filter([
            FilterKeyType::DATE_FROM->value => '2025-09-28 12:05:00',
            FilterKeyType::DATE_TO->value => '2025-09-28 12:10:00',
        ]);

        /* ASSERT */
        $this->assertCount(3, $firstResult);
        $this->assertCount(3, $secondResult);
        $this->assertCount(2, $thirdResult);
    }

    #[Test]
    public function it_filters_by_multiple_criteria(): void
    {
        /* EXECUTE */
        $results = $this->fileReader->filter([
            FilterKeyType::LEVEL->value => 'error',
            FilterKeyType::CHANNEL->value => 'local',
            FilterKeyType::DATE_FROM->value => '2025-09-28 12:00:00',
        ]);

        /* ASSERT */
        $this->assertCount(1, $results);
        $this->assertSame('ERROR', $results[0]->level);
    }

    #[Test]
    public function it_handles_empty_log_file(): void
    {
        /* SETUP */
        file_put_contents($this->logFile, '');

        /* EXECUTE */
        $result = $this->invokeParse($this->fileReader);

        /* ASSERT */
        $this->assertSame([], $result);
    }

    #[Test]
    public function it_searches_logs_using_chunking(): void
    {
        /* SETUP: force small chunk size so chunking happens */
        config(['laravel-log-reader.file.chunk_size' => 64]);

        /* EXECUTE: enable chunking */
        $results = $this->fileReader->search('User authentication', true);

        /* ASSERT */
        $this->assertCount(1, $results);
        $this->assertSame('INFO', $results[0]->level);
        $this->assertStringContainsString('User authentication successful', $results[0]->message);
    }

    #[Test]
    public function it_returns_same_results_with_and_without_chunking(): void
    {
        /* SETUP */
        config(['laravel-log-reader.file.chunk_size' => 64]);
    
        /* EXECUTE */
        $nonChunked = $this->fileReader->search('undefined method', false);
        $chunked = $this->fileReader->search('undefined method', true);
    
        /* ASSERT */
        $this->assertCount(count($nonChunked), $chunked);
    
        /* ASSERT */
        $nonChunkedMessages = array_map(fn($r) => $r->message, $nonChunked);
        $chunkedMessages = array_map(fn($r) => $r->message, $chunked);
    
        $this->assertSame($nonChunkedMessages, $chunkedMessages);
    }

    #[Test]
    public function it_reads_all_logs_correctly_when_chunking_with_unique_data(): void
    {
        /* SETUP */
        config(['laravel-log-reader.file.chunk_size' => 64]);
        $logFile = storage_path('logs/test_chunked.log');
        if (! is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }
        $total = 5;
        $lines = [];
        for ($i = 1; $i <= $total; $i++) {
            $lines[] = sprintf(
                "[%s] local.INFO: Chunk test message #%d",
                now()->subSeconds($total - $i)->format('Y-m-d H:i:s'),
                $i
            );
        }
        file_put_contents($logFile, implode(PHP_EOL, $lines));
        $reader = new \MoeMizrak\LaravelLogReader\Readers\FileLogReader($logFile);

        /* EXECUTE */
        $results = $reader->search('Chunk test message', true);

        /* ASSERT */
        $this->assertCount($total, $results, 'Expected all log entries to be returned');
        $expectedMessages = [];
        for ($i = 1; $i <= $total; $i++) {
            $expectedMessages[] = "Chunk test message #{$i}";
        }
        $messages = array_map(fn($r) => $r->message, $results);
        $this->assertSame($expectedMessages, $messages);
    }

    /**
     * Creates a temporary log file with sample log entries for testing.
     */
    private function createLogFile(): void
    {
        $content = <<<'LOGS'
[2025-09-28 12:00:00] local.INFO: User authentication successful {"user_id":123,"ip":"193.167.1.1","user_agent":"Mozilla/5.0"}
[2025-09-28 12:05:00] local.ERROR: Call to undefined method App\Models\User::nonExistentMethod() {"exception":"[object] (BadMethodCallException(code: 0): Call to undefined method App\\Models\\User::nonExistentMethod() at /var/www/html/app/Http/Controllers/UserController.php:25)
Stack trace:
#0 /var/www/html/app/Http/Controllers/UserController.php(25): Illuminate\\Database\\Eloquent\\Model::__call('nonExistentMeth...', Array)
#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Controller.php(54): App\\Http\\Controllers\\UserController->show(Object(Illuminate\\Http\\Request))
#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php(43): Illuminate\\Routing\\Controller->callAction('show', Array)
#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Route.php(260): Illuminate\\Routing\\ControllerDispatcher->dispatch(Object(Illuminate\\Routing\\Route), Object(App\\Http\\Controllers\\UserController), 'show')
#4 {main}","user_id":456,"request_data":{"id":"789"}}
[2025-09-28 12:10:00] local.DEBUG: Database query executed {"query":"SELECT * FROM users WHERE id = ?","bindings":[123],"time":45.67}
[2025-09-28 12:15:00] production.WARNING: Queue job failed {"job":"App\\Jobs\\SendEmailJob","attempts":3,"exception":"Connection timeout after 30 seconds"}
LOGS;

        file_put_contents($this->logFile, $content);
    }

    /**
     * Use reflection to invoke the protected parseLogFile method for testing.
     */
    private function invokeParse(FileLogReader $reader): array
    {
        $reflection = new \ReflectionClass($reader);
        $method = $reflection->getMethod('parseLogFile');
        $method->setAccessible(true);

        return $method->invoke($reader);
    }

    /**
     * This method searches for a log entry by its level name in order to assert the result.
     */
    private function findLogByLevel(array $logs, string $level): mixed
    {
        return array_find($logs, fn($log) => $log->level === $level);
    }
}