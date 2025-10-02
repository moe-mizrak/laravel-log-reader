<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Tests\Readers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use MoeMizrak\LaravelLogReader\Enums\LogDriverType;
use MoeMizrak\LaravelLogReader\Readers\DatabaseLogReader;
use MoeMizrak\LaravelLogReader\Readers\LogReaderInterface;
use MoeMizrak\LaravelLogReader\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(DatabaseLogReader::class)]
final class DatabaseLogReaderTest extends TestCase
{
    use RefreshDatabase;

    private string $table = 'logs';
    private DatabaseLogReader $databaseLogReader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createLogsTable();
        $this->seedLogs();

        config([
            'laravel-log-reader.driver' => LogDriverType::DB->value,
            'laravel-log-reader.db.table' => $this->table,
            'laravel-log-reader.db.columns' => [
                'id' => 'id',
                'levelName' => 'level',
                'level' => 'level',
                'message' => 'message',
                'timestamp' => 'created_at',
                'channel' => 'channel',
                'context' => 'context',
                'extra' => 'extra',
            ],
            'laravel-log-reader.db.searchable_columns' => ['message', 'context'],
        ]);

        $this->databaseLogReader = app(LogReaderInterface::class);
    }

    #[Test]
    public function it_returns_empty_array_for_empty_search_query(): void
    {
        /* EXECUTE */
        $result = $this->databaseLogReader->search('');

        /* ASSERT */
        $this->assertSame([], $result);
    }

    #[Test]
    public function it_returns_all_logs_when_no_filters_provided(): void
    {
        /* EXECUTE */
        $result = $this->databaseLogReader->filter([]);

        /* ASSERT */
        $this->assertCount(3, $result);
    }

    #[Test]
    public function it_searches_logs_by_message(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->search('Payment');

        /* ASSERT */
        $this->assertCount(1, $results);
        $this->assertSame('Payment failed', $results[0]->message);
        $this->assertSame('ERROR', $results[0]->levelName);
    }

    #[Test]
    public function it_searches_logs_by_context(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->search('user_id":1');

        /* ASSERT */
        $this->assertCount(1, $results);
        $this->assertSame('User logged in', $results[0]->message);
    }

    #[Test]
    public function it_searches_case_insensitively(): void
    {
        /* EXECUTE */
        $firstResult = $this->databaseLogReader->search('payment');
        $secondResult = $this->databaseLogReader->search('PAYMENT');
        $thirdResult = $this->databaseLogReader->search('PaYmEnT');

        /* ASSERT */
        $this->assertCount(1, $firstResult);
        $this->assertCount(1, $secondResult);
        $this->assertCount(1, $thirdResult);
    }

    #[Test]
    public function it_searches_both_message_and_context(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->search('user_id');

        /* ASSERT */
        $this->assertCount(2, $results); // Found in context of 2 logs
    }

    #[Test]
    public function it_returns_empty_for_no_search_matches(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->search('nonexistent');

        /* ASSERT */
        $this->assertSame([], $results);
    }

    #[Test]
    public function it_filters_logs_by_level(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->filter(['level' => 'error']);

        /* ASSERT */
        $this->assertCount(1, $results);
        $this->assertSame('Payment failed', $results[0]->message);
        $this->assertSame('ERROR', $results[0]->levelName);
    }

    #[Test]
    public function it_filters_logs_by_level_case_insensitively(): void
    {
        /* EXECUTE */
        $firstResult = $this->databaseLogReader->filter(['level' => 'error']);
        $secondResult = $this->databaseLogReader->filter(['level' => 'ERROR']);
        $thirdResult = $this->databaseLogReader->filter(['level' => 'ErRoR']);

        /* ASSERT */
        $this->assertCount(1, $firstResult);
        $this->assertCount(1, $secondResult);
        $this->assertCount(1, $thirdResult);
    }

    #[Test]
    public function it_filters_logs_by_channel(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->filter(['channel' => 'auth']);

        /* ASSERT */
        $this->assertCount(1, $results);
        $this->assertSame('User logged in', $results[0]->message);
    }

    #[Test]
    public function it_filters_logs_by_date_from(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->filter([
            'date_from' => now()->subMinutes(6),
        ]);

        /* ASSERT */
        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_filters_logs_by_date_to(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->filter([
            'date_to' => now()->subMinutes(6),
        ]);

        /* ASSERT */
        $this->assertCount(1, $results);
        $this->assertSame('User logged in', $results[0]->message);
    }

    #[Test]
    public function it_filters_logs_by_date_range(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->filter([
            'date_from' => now()->subMinutes(6),
            'date_to' => now(),
        ]);

        /* ASSERT */
        $this->assertCount(2, $results);
        $this->assertSame('Cache cleared', $results[0]->message); // newest first
        $this->assertSame('Payment failed', $results[1]->message);
    }

    #[Test]
    public function it_filters_logs_by_multiple_criteria(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->filter([
            'level' => 'error',
            'channel' => 'payment',
            'date_from' => now()->subMinutes(6),
        ]);

        /* ASSERT */
        $this->assertCount(1, $results);
        $this->assertSame('Payment failed', $results[0]->message);
        $this->assertSame('ERROR', $results[0]->levelName);
    }

    #[Test]
    public function it_filters_by_custom_column(): void
    {
        /* SETUP */
        DB::table($this->table)->insert([
            'message' => 'Custom column test',
            'context' => '{}',
            'level' => 'info',
            'channel' => 'test',
            'created_at' => now(),
        ]);

        /* EXECUTE */
        $results = $this->databaseLogReader->filter(['channel' => 'test']);

        /* ASSERT */
        $this->assertCount(1, $results);
        $this->assertSame('Custom column test', $results[0]->message);
    }

    #[Test]
    public function it_orders_results_by_newest_first(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->filter([]);

        /* ASSERT */
        $this->assertCount(3, $results);
        $this->assertSame('Cache cleared', $results[0]->message);
        $this->assertSame('Payment failed', $results[1]->message);
        $this->assertSame('User logged in', $results[2]->message);
    }

    #[Test]
    public function it_works_with_custom_connection(): void
    {
        /* SETUP */
        $reader = new DatabaseLogReader($this->table, config('database.default'));

        /* EXECUTE */
        $results = $reader->filter([]);

        /* ASSERT */
        $this->assertCount(3, $results);
    }

    /**
     * Create the logs table for testing.
     */
    private function createLogsTable(): void
    {
        DB::statement("CREATE TABLE {$this->table} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message TEXT,
            context TEXT,
            level VARCHAR(20),
            channel VARCHAR(50),
            created_at DATETIME
        )");
    }

    /**
     * Seed the logs table with sample data for testing.
     */
    private function seedLogs(): void
    {
        DB::table($this->table)->insert([
            [
                'message' => 'User logged in',
                'context' => '{"user_id":1}',
                'level' => 'INFO',
                'channel' => 'auth',
                'created_at' => now()->subMinutes(10),
            ],
            [
                'message' => 'Payment failed',
                'context' => '{"user_id":2}',
                'level' => 'ERROR',
                'channel' => 'payment',
                'created_at' => now()->subMinutes(5),
            ],
            [
                'message' => 'Cache cleared',
                'context' => '{}',
                'level' => 'DEBUG',
                'channel' => 'system',
                'created_at' => now(),
            ],
        ]);
    }
}