<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Client\ConnectionException;
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

    public function fetchPaginated(string $endpoint, array $query = []): array
    {
        $page = 1;
        $all = [];

        do {
            $pageQuery = array_merge($query, [
                'page' => $page,
                'limit' => config('wb.limit'),
            ]);

            $response = $this->get($endpoint, $pageQuery);

            $items = $response['data'] ?? [];
            $all = array_merge($all, $items);

            $lastPage = (int) ($response['meta']['last_page'] ?? $page);

            $this->debug->line(sprintf(
                '%s: страница %d/%d, получено %d записей (всего %d)',
                $endpoint,
                $page,
                $lastPage,
                count($items),
                count($all)
            ));

            $page++;

            if ($page <= $lastPage) {
                $this->throttle();
            }
        } while ($page <= $lastPage);

        return $all;
    }

    public function get(string $endpoint, array $query = []): array
    {
        $query['key'] = config('wb.key');

        $url = rtrim(config('wb.base_url'), '/').'/api/'.ltrim($endpoint, '/');
        $maxAttempts = (int) config('wb.retry_attempts', 5);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->debug->line(sprintf(
                'GET %s attempt %d/%d %s',
                $endpoint,
                $attempt,
                $maxAttempts,
                json_encode($this->sanitizeQuery($query), JSON_UNESCAPED_UNICODE)
            ));

            try {
                $response = Http::timeout(config('wb.timeout'))
                    ->acceptJson()
                    ->get($url, $query);
            } catch (ConnectionException $e) {
                $this->debug->line("connection error: {$e->getMessage()}");

                if ($attempt >= $maxAttempts) {
                    throw new RuntimeException("WB API connection failed [{$endpoint}]: {$e->getMessage()}", 0, $e);
                }

                $this->pauseBeforeRetry($endpoint, $attempt, null);

                continue;
            }

            $this->debug->line("HTTP {$response->status()} {$endpoint}");

            if ($this->isRateLimited($response)) {
                if ($attempt >= $maxAttempts) {
                    $this->throwHttpError($endpoint, $response);
                }

                $this->pauseBeforeRetry($endpoint, $attempt, $response);

                continue;
            }

            if ($response->failed()) {
                $this->throwHttpError($endpoint, $response);
            }

            $this->throttle();

            return $response->json();
        }

        throw new RuntimeException("WB API error [{$endpoint}]: max retry attempts exceeded");
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

    private function pauseBeforeRetry(string $endpoint, int $attempt, ?Response $response): void
    {
        $seconds = $this->retryDelaySeconds($response, $attempt);

        $this->debug->line(sprintf(
            'rate limit on %s, attempt %d, wait %d s (HTTP %s)',
            $endpoint,
            $attempt,
            $seconds,
            $response?->status() ?? 'connection'
        ));

        Log::warning('WB API rate limit, retrying', [
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

    private function sanitizeQuery(array $query): array
    {
        if (isset($query['key'])) {
            $query['key'] = '***';
        }

        return $query;
    }

    private function throwHttpError(string $endpoint, Response $response): void
    {
        $body = $response->body();
        if (strlen($body) > 500) {
            $body = substr($body, 0, 500).'…';
        }

        throw new RuntimeException(
            "WB API error [{$endpoint}] HTTP {$response->status()}: {$body}"
        );
    }
}
