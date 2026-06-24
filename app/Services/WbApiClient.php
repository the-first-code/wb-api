<?php

namespace App\Services;

use App\Models\TokenType;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WbApiClient
{
    private int $postRateLimitDelayMs = 0;

    public function __construct(
        private readonly WbConsoleDebug $debug,
        private ?Closure $sleepSeconds = null,
    ) {}

    public function fetchPaginated(string $endpoint, array $query = [], ?WbApiContext $context = null): array
    {
        $all = [];

        $this->eachPaginatedPage(
            $endpoint,
            $query,
            function (array $items) use (&$all): void {
                foreach ($items as $item) {
                    $all[] = $item;
                }
            },
            $context
        );

        return $all;
    }

    /**
     * @param  callable(array<int, array<string, mixed>>, int, int): void  $onPage
     */
    public function eachPaginatedPage(
        string $endpoint,
        array $query,
        callable $onPage,
        ?WbApiContext $context = null,
    ): int {
        $context ??= WbApiContext::fromEnv();
        $page = 1;
        $totalItems = 0;

        do {
            $pageQuery = array_merge($query, [
                'page' => $page,
                'limit' => config('wb.limit'),
            ]);

            $response = $this->get($endpoint, $pageQuery, $context);

            $items = $response['data'] ?? [];
            $lastPage = (int) ($response['meta']['last_page'] ?? $page);
            $totalItems += count($items);

            $onPage($items, $page, $lastPage);

            $this->debug->line(sprintf(
                '[%s] %s: страница %d/%d, получено %d записей (всего %d)',
                $context->accountName,
                $endpoint,
                $page,
                $lastPage,
                count($items),
                $totalItems
            ));

            $page++;

            if ($page <= $lastPage) {
                $this->throttle();
            }
        } while ($page <= $lastPage);

        return $totalItems;
    }

    public function get(string $endpoint, array $query = [], ?WbApiContext $context = null): array
    {
        $context ??= WbApiContext::fromEnv();

        $url = $context->baseUrl.'/api/'.ltrim($endpoint, '/');
        $maxAttempts = (int) config('wb.retry_attempts', 5);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $requestQuery = $query;

            $this->debug->line(sprintf(
                '[%s] GET %s attempt %d/%d %s',
                $context->accountName,
                $endpoint,
                $attempt,
                $maxAttempts,
                json_encode($this->sanitizeQuery($requestQuery, $context), JSON_UNESCAPED_UNICODE)
            ));

            try {
                $response = $this->buildRequest($context)
                    ->get($url, $this->applyQueryAuth($requestQuery, $context));
            } catch (ConnectionException $e) {
                $this->debug->line("connection error: {$e->getMessage()}");

                if ($attempt >= $maxAttempts) {
                    throw new RuntimeException("WB API connection failed [{$endpoint}]: {$e->getMessage()}", 0, $e);
                }

                $this->pauseBeforeRetry($endpoint, $attempt, null, $context);

                continue;
            }

            $this->debug->line("HTTP {$response->status()} {$endpoint}");

            if ($this->isRateLimited($response)) {
                if ($attempt >= $maxAttempts) {
                    $this->throwHttpError($endpoint, $response, $context);
                }

                $this->pauseBeforeRetry($endpoint, $attempt, $response, $context);

                continue;
            }

            if ($response->failed()) {
                $this->throwHttpError($endpoint, $response, $context);
            }

            $this->throttle();

            return $response->json();
        }

        throw new RuntimeException("WB API error [{$endpoint}] account «{$context->accountName}»: max retry attempts exceeded");
    }

    private function buildRequest(WbApiContext $context): PendingRequest
    {
        $request = Http::timeout(config('wb.timeout'))->acceptJson();

        return match ($context->tokenTypeCode) {
            TokenType::BEARER => $request->withToken((string) ($context->credentials['token'] ?? '')),
            TokenType::API_KEY => $request->withHeaders([
                (string) ($context->credentials['header'] ?? 'X-Api-Key') => (string) ($context->credentials['value'] ?? ''),
            ]),
            TokenType::QUERY_KEY => $request,
            TokenType::BASIC_AUTH => $request->withBasicAuth(
                (string) ($context->credentials['username'] ?? ''),
                (string) ($context->credentials['password'] ?? ''),
            ),
            default => throw new RuntimeException(
                "Неподдерживаемый тип токена «{$context->tokenTypeCode}» для HTTP-запросов."
            ),
        };
    }

    private function applyQueryAuth(array $query, WbApiContext $context): array
    {
        if ($context->tokenTypeCode !== TokenType::QUERY_KEY) {
            return $query;
        }

        $param = (string) ($context->credentials['param'] ?? 'key');
        $query[$param] = (string) ($context->credentials['value'] ?? '');

        return $query;
    }

    private function isRateLimited(Response $response): bool
    {
        if ($response->status() === 429) {
            return true;
        }

        return $this->responseIndicatesRateLimit($response);
    }

    private function responseIndicatesRateLimit(Response $response): bool
    {
        $phrases = ['too many requests', 'rate limit', 'rate_limit'];

        $body = strtolower($response->body());
        foreach ($phrases as $phrase) {
            if (str_contains($body, $phrase)) {
                return true;
            }
        }

        $json = $response->json();
        if (! is_array($json)) {
            return false;
        }

        foreach (['message', 'error', 'detail'] as $field) {
            $value = strtolower((string) ($json[$field] ?? ''));
            foreach ($phrases as $phrase) {
                if ($value !== '' && str_contains($value, $phrase)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function pauseBeforeRetry(string $endpoint, int $attempt, ?Response $response, WbApiContext $context): void
    {
        $seconds = $this->retryDelaySeconds($response, $attempt);

        $this->debug->line(sprintf(
            '[%s] rate limit on %s, attempt %d, wait %d s (HTTP %s)',
            $context->accountName,
            $endpoint,
            $attempt,
            $seconds,
            $response?->status() ?? 'connection'
        ));

        Log::warning('WB API rate limit, retrying', [
            'account' => $context->accountName,
            'endpoint' => $endpoint,
            'attempt' => $attempt,
            'status' => $response?->status(),
            'wait_seconds' => $seconds,
        ]);

        $this->pauseSeconds($seconds);
        $this->postRateLimitDelayMs = (int) config('wb.rate_limit_penalty_ms', 1000);
    }

    private function retryDelaySeconds(?Response $response, int $attempt): int
    {
        if ($response !== null) {
            foreach (['Retry-After', 'X-RateLimit-Retry', 'X-Ratelimit-Retry'] as $header) {
                $value = (int) $response->header($header);
                if ($value > 0) {
                    return min($value, (int) config('wb.retry_max_seconds', 60));
                }
            }
        }

        $base = (int) config('wb.retry_base_seconds', 2);
        $delay = $base * (2 ** ($attempt - 1));

        return min(max(1, $delay), (int) config('wb.retry_max_seconds', 60));
    }

    private function throttle(): void
    {
        $ms = (int) config('wb.request_delay_ms', 350) + $this->postRateLimitDelayMs;
        $this->postRateLimitDelayMs = 0;

        if ($ms > 0) {
            $this->debug->line("throttle {$ms} ms");
            usleep($ms * 1000);
        }
    }

    private function pauseSeconds(int $seconds): void
    {
        if ($this->sleepSeconds !== null) {
            ($this->sleepSeconds)($seconds);

            return;
        }

        sleep(max(1, $seconds));
    }

    private function sanitizeQuery(array $query, WbApiContext $context): array
    {
        if ($context->tokenTypeCode === TokenType::QUERY_KEY) {
            $param = (string) ($context->credentials['param'] ?? 'key');
            if (array_key_exists($param, $query)) {
                $query[$param] = '***';
            }
        }

        return $query;
    }

    private function throwHttpError(string $endpoint, Response $response, WbApiContext $context): void
    {
        $body = $response->body();
        if (strlen($body) > 500) {
            $body = substr($body, 0, 500).'…';
        }

        throw new RuntimeException(
            "WB API error [{$endpoint}] account «{$context->accountName}» HTTP {$response->status()}: {$body}"
        );
    }
}
