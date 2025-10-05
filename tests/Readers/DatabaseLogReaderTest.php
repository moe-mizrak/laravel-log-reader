<?php

declare(strict_types=1);

namespace MoeMizrak\LaravelLogReader\Tests\Readers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use MoeMizrak\LaravelLogReader\Enums\FilterKeyType;
use MoeMizrak\LaravelLogReader\Enums\LogDriverType;
use MoeMizrak\LaravelLogReader\Enums\LogTableColumnType;
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
                LogTableColumnType::ID->value => 'id',
                LogTableColumnType::LEVEL->value => 'level',
                LogTableColumnType::MESSAGE->value => 'message',
                LogTableColumnType::TIMESTAMP->value => 'created_at',
                LogTableColumnType::CHANNEL->value => 'channel',
                LogTableColumnType::CONTEXT->value => 'context',
                LogTableColumnType::EXTRA->value => 'extra',
            ],
            'laravel-log-reader.db.searchable_columns' => [
                LogTableColumnType::MESSAGE->value,
                LogTableColumnType::CONTEXT->value,
                LogTableColumnType::EXTRA->value,
            ],
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
    }

    #[Test]
    public function it_searches_logs_by_extra(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->search('"user_id":1');

        /* ASSERT */
        $this->assertCount(1, $results);
        $this->assertSame('User logged in', $results[0]->message);
    }

    #[Test]
    public function it_searches_logs_by_context(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->search('"action":"login"');

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
    public function it_searches_both_message_and_extra(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->search('user_id');

        /* ASSERT */
        $this->assertCount(2, $results);
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
        $results = $this->databaseLogReader->filter([FilterKeyType::LEVEL->value => 'error']);

        /* ASSERT */
        $this->assertCount(1, $results);
        $this->assertSame('Payment failed', $results[0]->message);
        $this->assertSame('ERROR', $results[0]->level);
    }

    #[Test]
    public function it_filters_logs_by_level_case_insensitively(): void
    {
        /* EXECUTE */
        $firstResult = $this->databaseLogReader->filter([FilterKeyType::LEVEL->value => 'error']);
        $secondResult = $this->databaseLogReader->filter([FilterKeyType::LEVEL->value => 'ERROR']);
        $thirdResult = $this->databaseLogReader->filter([FilterKeyType::LEVEL->value => 'ErRoR']);

        /* ASSERT */
        $this->assertCount(1, $firstResult);
        $this->assertCount(1, $secondResult);
        $this->assertCount(1, $thirdResult);
    }

    #[Test]
    public function it_filters_logs_by_channel(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->filter([FilterKeyType::CHANNEL->value => 'auth']);

        /* ASSERT */
        $this->assertCount(1, $results);
        $this->assertSame('User logged in', $results[0]->message);
        $this->assertSame('auth', $results[0]->channel);
    }

    #[Test]
    public function it_filters_logs_by_date_from(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->filter([
            FilterKeyType::DATE_FROM->value => now()->subMinutes(6),
        ]);

        /* ASSERT */
        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_filters_logs_by_date_to(): void
    {
        /* EXECUTE */
        $results = $this->databaseLogReader->filter([
            FilterKeyType::DATE_TO->value => now()->subMinutes(6),
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
            FilterKeyType::DATE_FROM->value => now()->subMinutes(6),
            FilterKeyType::DATE_TO->value => now(),
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
            FilterKeyType::LEVEL->value => 'error',
            FilterKeyType::CHANNEL->value => 'payment',
            FilterKeyType::DATE_FROM->value => now()->subMinutes(6),
        ]);

        /* ASSERT */
        $this->assertCount(1, $results);
        $this->assertSame('Payment failed', $results[0]->message);
        $this->assertSame('ERROR', $results[0]->level);
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
        $results = $this->databaseLogReader->filter([FilterKeyType::CHANNEL->value => 'test']);

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
    public function it_parses_extra_json_column_into_array(): void
    {
        /* SETUP */
        DB::table($this->table)->insert([
            'message' => 'With extra',
            'context' => '{}',
            'extra' => json_encode(['file' => '/app/Job.php', 'line' => 42]),
            'level' => 'info',
            'channel' => 'job',
            'created_at' => now(),
        ]);

        /* EXECUTE */
        $results = $this->databaseLogReader->filter([FilterKeyType::CHANNEL->value => 'job']);

        /* ASSERT */
        $this->assertCount(1, $results);
        $this->assertIsArray($results[0]->extra);
        $this->assertSame('/app/Job.php', $results[0]->extra['file']);
        $this->assertSame(42, $results[0]->extra['line']);
    }

    #[Test]
    public function it_decodes_extra_with_user_and_request_metadata(): void
    {
        /* SETUP */
        $userId = 99;
        $ipAddress = '203.0.113.10';
        $requestId = 'req-123abc';
        $action = 'profile_update';
        DB::table($this->table)->insert([
            'message' => 'User metadata logged',
            'context' => json_encode(['action' => $action]),
            'extra' => json_encode([
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'request_id' => $requestId,
            ]),
            'level' => 'info',
            'channel' => 'user',
            'created_at' => now(),
        ]);

        /* EXECUTE */
        $results = $this->databaseLogReader->filter([
            FilterKeyType::CHANNEL->value => 'user',
        ]);

        /* ASSERT */
        $this->assertCount(1, $results);
        $log = $results[0];

        $this->assertSame('User metadata logged', $log->message);
        $this->assertIsArray($log->extra);
        $this->assertSame($userId, $log->extra['user_id']);
        $this->assertSame($ipAddress, $log->extra['ip_address']);
        $this->assertSame($requestId, $log->extra['request_id']);
        $this->assertSame(json_encode(['action' => $action]), $log->context);
    }

    #[Test]
    public function it_handles_missing_extra_metadata_gracefully(): void
    {
        /* SETUP */
        $action = 'logout';
        $message = 'Missing metadata test';
        DB::table($this->table)->insert([
            'message' => $message,
            'context' => json_encode(['action' => $action]),
            'extra' => null, // no metadata
            'level' => 'info',
            'channel' => 'user',
            'created_at' => now(),
        ]);

        /* EXECUTE */
        $results = $this->databaseLogReader->filter([
            FilterKeyType::CHANNEL->value => 'user',
        ]);

        /* ASSERT */
        $this->assertCount(1, $results);
        $log = $results[0];
        $this->assertSame($message, $log->message);
        $this->assertSame(json_encode(['action' => $action]), $log->context);
        $this->assertIsArray($log->extra);
        $this->assertSame([], $log->extra, 'Extra should be an empty array when missing or null');
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
            level VARCHAR(20),
            message TEXT,
            channel VARCHAR(50),
            context TEXT,
            extra TEXT,
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
                'level' => 'INFO',
                'message' => 'User logged in',
                'channel' => 'auth',
                'context' => '{"action":"login"}',
                'extra' => '{"user_id":1}',
                'created_at' => now()->subMinutes(10),
            ],
            [
                'level' => 'ERROR',
                'message' => 'Payment failed',
                'channel' => 'payment',
                'context' => '{}',
                'extra' => '{"user_id":2}',
                'created_at' => now()->subMinutes(5),
            ],
            [
                'level' => 'DEBUG',
                'message' => 'Cache cleared',
                'channel' => 'system',
                'context' => '{}',
                'extra' => '{}',
                'created_at' => now(),
            ],
        ]);
    }
}
