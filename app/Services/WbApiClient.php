<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WbApiClient
{
    public function fetchPaginated(string $endpoint, array $query = []): array
    {
        $page = 1;
        $all = [];

        do {
            $response = $this->get($endpoint, array_merge($query, [
                'page' => $page,
                'limit' => config('wb.limit'),
            ]));

            $items = $response['data'] ?? [];
            $all = array_merge($all, $items);

            $lastPage = (int) ($response['meta']['last_page'] ?? $page);
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
            try {
                $response = Http::timeout(config('wb.timeout'))
                    ->acceptJson()
                    ->get($url, $query);
            } catch (ConnectionException $e) {
                if ($attempt >= $maxAttempts) {
                    throw new RuntimeException("WB API connection failed [{$endpoint}]: {$e->getMessage()}", 0, $e);
                }

                $this->sleepBackoff($attempt);

                continue;
            }

            if ($response->status() === 429) {
                if ($attempt >= $maxAttempts) {
                    $this->throwHttpError($endpoint, $response);
                }

                $retryAfter = (int) $response->header('Retry-After');
                $this->sleepBackoff($attempt, $retryAfter > 0 ? $retryAfter : null);

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

    private function throttle(): void
    {
        $ms = (int) config('wb.request_delay_ms', 350);
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }

    private function sleepBackoff(int $attempt, ?int $retryAfterSeconds = null): void
    {
        $seconds = $retryAfterSeconds ?? min(60, (int) config('wb.retry_base_seconds', 2) * $attempt);
        sleep(max(1, $seconds));
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
