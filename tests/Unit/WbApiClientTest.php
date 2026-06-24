<?php

namespace Tests\Unit;

use App\Services\WbApiClient;
use App\Services\WbConsoleDebug;
use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class WbApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('wb.base_url', 'http://wb.test');
        Config::set('wb.key', 'test-key');
        Config::set('wb.timeout', 5);
        Config::set('wb.limit', 500);
        Config::set('wb.retry_attempts', 3);
        Config::set('wb.retry_base_seconds', 2);
        Config::set('wb.retry_max_seconds', 60);
        Config::set('wb.request_delay_ms', 0);
        Config::set('wb.rate_limit_penalty_ms', 0);
    }

    public function test_retries_on_http_429_and_returns_successful_response(): void
    {
        $sleeps = [];
        $client = $this->makeClient(function (int $seconds) use (&$sleeps): void {
            $sleeps[] = $seconds;
        });

        Http::fake([
            'http://wb.test/api/orders*' => Http::sequence()
                ->push(['message' => 'Too Many Requests'], 429, ['Retry-After' => '3'])
                ->push(['data' => [['id' => 1]], 'meta' => ['last_page' => 1]], 200),
        ]);

        $result = $client->get('orders', ['dateFrom' => '2025-01-01']);

        $this->assertSame([['id' => 1]], $result['data']);
        $this->assertSame([3], $sleeps);
        Http::assertSentCount(2);
    }

    public function test_retries_when_response_body_contains_too_many_requests(): void
    {
        $sleeps = [];
        $client = $this->makeClient(function (int $seconds) use (&$sleeps): void {
            $sleeps[] = $seconds;
        });

        Http::fake([
            'http://wb.test/api/sales*' => Http::sequence()
                ->push(['message' => 'Too many requests'], 503)
                ->push(['data' => [], 'meta' => ['last_page' => 1]], 200),
        ]);

        $client->get('sales', ['dateFrom' => '2025-01-01']);

        $this->assertSame([2], $sleeps);
        Http::assertSentCount(2);
    }

    public function test_throws_after_max_retry_attempts_on_rate_limit(): void
    {
        $client = $this->makeClient(fn (): null => null);

        Http::fake([
            'http://wb.test/api/stocks*' => Http::response(['message' => 'Too Many Requests'], 429),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 429');

        $client->get('stocks', ['dateFrom' => '2025-01-01']);
    }

    public function test_writes_debug_lines_when_enabled(): void
    {
        $output = new BufferedOutput;
        $debug = new WbConsoleDebug;
        $debug->enable($output);

        $client = new WbApiClient($debug);

        Http::fake([
            'http://wb.test/api/orders*' => Http::response(['data' => [], 'meta' => ['last_page' => 1]], 200),
        ]);

        $client->get('orders', ['dateFrom' => '2025-01-01']);

        $written = $output->fetch();
        $this->assertStringContainsString('[debug] [.env] GET orders', $written);
        $this->assertStringContainsString('HTTP 200 orders', $written);
    }

    public function test_uses_account_credentials_in_query(): void
    {
        Http::fake([
            'http://wb.test/api/orders*' => Http::response(['data' => [], 'meta' => ['last_page' => 1]], 200),
        ]);

        $context = new \App\Services\WbApiContext(
            accountId: 5,
            accountName: 'Кабинет 1',
            baseUrl: 'http://wb.test',
            tokenTypeCode: \App\Models\TokenType::QUERY_KEY,
            credentials: ['param' => 'key', 'value' => 'account-secret'],
        );

        $this->makeClient()->get('orders', ['dateFrom' => '2025-01-01'], $context);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://wb.test/api/orders?dateFrom=2025-01-01&key=account-secret';
        });
    }

    private function makeClient(?Closure $sleep = null): WbApiClient
    {
        return new WbApiClient(new WbConsoleDebug, $sleep);
    }
}
