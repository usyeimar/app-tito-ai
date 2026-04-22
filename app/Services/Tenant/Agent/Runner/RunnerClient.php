<?php

declare(strict_types=1);

namespace App\Services\Tenant\Agent\Runner;

use App\Models\Tenant\Agent\Agent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Hybrid client for the Tito AI Runners microservice.
 *
 * - HTTP for synchronous operations (session.create) — needs immediate response.
 * - Redis for async fire-and-forget commands (session.terminate).
 *
 * @see services/runners/app/api/v1/sessions.py
 */
final class RunnerClient
{
    private const MAX_RETRIES = 3;

    private const BASE_DELAY_MS = 200;

    public function __construct(
        private readonly AgentConfigBuilder $configBuilder,
        private readonly RunnerCommandBus $commandBus,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly ?RunnerRegistry $runnerRegistry = null,
    ) {}

    /**
     * Create a new voice session via HTTP (synchronous).
     *
     * @return array{
     *     session_id: string,
     *     room_name: string,
     *     provider: string,
     *     url: string,
     *     access_token: string,
     *     context: array<string, mixed>,
     * }
     */
    public function createSession(Agent $agent): array
    {
        $this->ensureAvailable();

        $config = $this->configBuilder->build($agent);

        return $this->withRetry(function () use ($config): array {
            $response = $this->httpRequest()
                ->post('/api/v1/sessions/', $config);

            if ($response->failed()) {
                Log::warning('Runner session create failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $message = (string) ($response->json('detail.message')
                    ?? $response->json('message')
                    ?? $response->json('detail')
                    ?? 'Failed to create runner session');

                throw new RuntimeException($message);
            }

            /** @var array<string, mixed> $payload */
            $payload = $response->json();

            return [
                'session_id' => (string) ($payload['session_id'] ?? ''),
                'room_name' => (string) ($payload['room_name'] ?? ''),
                'provider' => (string) ($payload['provider'] ?? ''),
                'url' => (string) ($payload['ws_url'] ?? $payload['url'] ?? ''),
                'access_token' => (string) ($payload['access_token'] ?? ''),
                'context' => (array) ($payload['context'] ?? []),
            ];
        });
    }

    /**
     * Terminate a session via Redis (async, fire-and-forget).
     */
    public function terminateSession(string $sessionId, ?string $hostId = null): bool
    {
        try {
            $this->commandBus->dispatch('session.terminate', [
                'session_id' => $sessionId,
                'host_id' => $hostId,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Runner session terminate failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withRetry(callable $callback): mixed
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $result = $callback();
                $this->circuitBreaker->recordSuccess();

                return $result;
            } catch (ConnectionException $e) {
                $lastException = $e;
                $this->circuitBreaker->recordFailure();
                Log::warning('Runner connection failed', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            } catch (RuntimeException $e) {
                $this->circuitBreaker->recordFailure();
                throw $e;
            }

            if ($attempt < self::MAX_RETRIES) {
                usleep(self::BASE_DELAY_MS * (2 ** ($attempt - 1)) * 1000);
            }
        }

        throw new RuntimeException(
            'Runner unavailable after '.self::MAX_RETRIES.' attempts: '.$lastException?->getMessage(),
            previous: $lastException,
        );
    }

    private function ensureAvailable(): void
    {
        if (! $this->circuitBreaker->isAvailable()) {
            throw new RuntimeException(
                'Runner service circuit breaker is open. Service temporarily unavailable.'
            );
        }
    }

    private function getRunnerUrl(): string
    {
        if (config('runners.use_registry', false) && $this->runnerRegistry) {
            $runner = $this->runnerRegistry->getAvailableRunner();
            if ($runner && ($runner['url'] ?? '')) {
                return $runner['url'];
            }
        }

        return rtrim((string) config('runners.base_url', 'http://localhost:8000'), '/');
    }

    private function httpRequest(): PendingRequest
    {
        $request = Http::baseUrl($this->getRunnerUrl())
            ->timeout((int) config('runners.timeout', 15))
            ->acceptJson()
            ->asJson();

        $apiKey = config('runners.api_key');
        if (is_string($apiKey) && $apiKey !== '') {
            $request = $request->withToken($apiKey);
        }

        return $request;
    }
}
