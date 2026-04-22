<?php

declare(strict_types=1);

namespace App\Services\Tenant\Agent\Runner;

use App\Models\Tenant\Agent\Agent;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Client for the Tito AI Runners microservice.
 *
 * Dispatches session commands via Redis (RunnerCommandBus).
 * The runner consumes commands from the Redis queue, processes them,
 * and pushes responses back for Laravel to read.
 *
 * @see services/runners/app/api/v1/sessions.py
 */
final class RunnerClient
{
    public function __construct(
        private readonly AgentConfigBuilder $configBuilder,
        private readonly RunnerCommandBus $commandBus,
        private readonly CircuitBreaker $circuitBreaker,
    ) {}

    /**
     * Create a new voice session for the given agent.
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

        try {
            $response = $this->commandBus->dispatch('session.create', $config);
            $this->circuitBreaker->recordSuccess();
        } catch (RuntimeException $e) {
            $this->circuitBreaker->recordFailure();
            Log::warning('Runner session create failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        $data = $response['data'] ?? $response;

        return [
            'session_id' => (string) ($data['session_id'] ?? ''),
            'room_name' => (string) ($data['room_name'] ?? ''),
            'provider' => (string) ($data['provider'] ?? ''),
            'url' => (string) ($data['ws_url'] ?? $data['url'] ?? ''),
            'access_token' => (string) ($data['access_token'] ?? ''),
            'context' => (array) ($data['context'] ?? []),
        ];
    }

    /**
     * Terminate a session.
     */
    public function terminateSession(string $sessionId, ?string $hostId = null): bool
    {
        try {
            $this->commandBus->dispatchAsync('session.terminate', [
                'session_id' => $sessionId,
                'host_id' => $hostId,
            ]);

            return true;
        } catch (RuntimeException $e) {
            Log::warning('Runner session terminate failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function ensureAvailable(): void
    {
        if (! $this->circuitBreaker->isAvailable()) {
            throw new RuntimeException(
                'Runner service circuit breaker is open. Service temporarily unavailable.'
            );
        }
    }
}
