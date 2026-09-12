<?php

namespace App\Services\Exchanges\Adapters;

use App\Services\Exchanges\ExchangeInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseAdapter implements ExchangeInterface
{
    protected array $credentials;
    protected string $baseUrl;
    protected int $rateLimitMs = 100;
    protected int $requestCount = 0;
    protected int $lastRequestTime = 0;

    abstract protected function signRequest(array $params): array;
    abstract protected function getBaseUrls(): array;

    public function __construct(array $credentials)
    {
        $this->credentials = $credentials;
        $urls = $this->getBaseUrls();
        $this->baseUrl = $urls['rest'];
    }

    protected function throttle(): void
    {
        $now = microtime(true) * 1000;
        $elapsed = $now - $this->lastRequestTime;
        if ($elapsed < $this->rateLimitMs) {
            usleep(($this->rateLimitMs - $elapsed) * 1000);
        }
        $this->lastRequestTime = microtime(true) * 1000;
    }

    protected function get(string $endpoint, array $params = [], bool $signed = false): array
    {
        $this->throttle();

        if ($signed) {
            $params = $this->signRequest($params);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders($params['headers'] ?? [])
                ->get($this->baseUrl . $endpoint, $params['query'] ?? []);

            $this->requestCount++;

            if ($response->failed()) {
                Log::error("Exchange API GET failed", [
                    'exchange' => $this->getName(),
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException("API request failed: " . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Exchange API GET error", [
                'exchange' => $this->getName(),
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function post(string $endpoint, array $data = [], bool $signed = true): array
    {
        $this->throttle();

        if ($signed) {
            $data = $this->signRequest($data);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders($data['headers'] ?? [])
                ->post($this->baseUrl . $endpoint, $data['body'] ?? []);

            $this->requestCount++;

            if ($response->failed()) {
                Log::error("Exchange API POST failed", [
                    'exchange' => $this->getName(),
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException("API request failed: " . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Exchange API POST error", [
                'exchange' => $this->getName(),
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function delete(string $endpoint, array $params = [], bool $signed = true): array
    {
        $this->throttle();

        if ($signed) {
            $params = $this->signRequest($params);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders($params['headers'] ?? [])
                ->delete($this->baseUrl . $endpoint, $params['query'] ?? []);

            $this->requestCount++;

            if ($response->failed()) {
                throw new \RuntimeException("API request failed: " . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Exchange API DELETE error", [
                'exchange' => $this->getName(),
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
