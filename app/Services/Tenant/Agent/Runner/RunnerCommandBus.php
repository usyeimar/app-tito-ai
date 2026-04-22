<?php

declare(strict_types=1);

namespace App\Services\Tenant\Agent\Runner;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Dispatches commands to runners via Redis and waits for responses.
 *
 * Protocol:
 *   1. Laravel LPUSHes a JSON command to `runner:commands` (or a specific runner channel).
 *   2. The runner BRPOPs from that list, processes the command.
 *   3. The runner LPUSHes the JSON response to `runner:responses:{request_id}`.
 *   4. Laravel BRPOPs from the response key with a timeout.
 *
 * This replaces direct HTTP calls, fully decoupling Laravel from the runner process.
 */
final class RunnerCommandBus
{
    private const COMMANDS_KEY = 'runner:commands';

    private const RESPONSE_KEY_PREFIX = 'runner:responses:';

    public function __construct(
        private readonly ?RunnerRegistry $runnerRegistry = null,
    ) {}

    /**
     * Dispatch a command and wait for the runner's response.
     *
     * @param  array<string, mixed>  $payload  Command payload
     * @param  int  $timeoutSeconds  Max seconds to wait for response
     * @return array<string, mixed> Runner response
     *
     * @throws RuntimeException When no response is received within timeout
     */
    public function dispatch(string $command, array $payload, int $timeoutSeconds = 0): array
    {
        $timeout = $timeoutSeconds > 0 ? $timeoutSeconds : (int) config('runners.redis.response_timeout', 15);
        $requestId = Str::ulid()->toString();

        $message = json_encode([
            'request_id' => $requestId,
            'command' => $command,
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
        ]);

        $commandsKey = $this->resolveCommandsKey();

        Log::debug('Dispatching runner command', [
            'command' => $command,
            'request_id' => $requestId,
            'channel' => $commandsKey,
        ]);

        $this->redis()->lpush($commandsKey, [$message]);

        return $this->waitForResponse($requestId, $timeout);
    }

    /**
     * Dispatch a fire-and-forget command (no response expected).
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatchAsync(string $command, array $payload): void
    {
        $message = json_encode([
            'request_id' => Str::ulid()->toString(),
            'command' => $command,
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
            'async' => true,
        ]);

        $this->redis()->lpush($this->resolveCommandsKey(), [$message]);
    }

    /**
     * Wait for a response on the response key using BRPOP (blocking pop).
     *
     * @return array<string, mixed>
     */
    private function waitForResponse(string $requestId, int $timeoutSeconds): array
    {
        $responseKey = self::RESPONSE_KEY_PREFIX.$requestId;

        /** @var array|null $result */
        $result = $this->redis()->brpop([$responseKey], $timeoutSeconds);

        if ($result === null) {
            Log::error('Runner command timed out', [
                'request_id' => $requestId,
                'timeout' => $timeoutSeconds,
            ]);

            throw new RuntimeException(
                "Runner did not respond within {$timeoutSeconds}s (request_id: {$requestId})"
            );
        }

        // BRPOP returns [key, value]
        $raw = is_array($result) ? ($result[1] ?? $result[0] ?? '') : $result;
        $response = json_decode((string) $raw, true);

        if (! is_array($response)) {
            throw new RuntimeException("Invalid runner response for request {$requestId}");
        }

        if (! empty($response['error'])) {
            throw new RuntimeException((string) $response['error']);
        }

        Log::debug('Runner command response received', [
            'request_id' => $requestId,
            'command' => $response['command'] ?? 'unknown',
        ]);

        return $response;
    }

    /**
     * Resolve which Redis key to push commands to.
     *
     * If registry is enabled, routes to the least-loaded runner's channel.
     * Otherwise uses the shared commands key.
     */
    private function resolveCommandsKey(): string
    {
        if (config('runners.use_registry', false) && $this->runnerRegistry) {
            $runner = $this->runnerRegistry->getAvailableRunner();

            if ($runner && ($runner['host_id'] ?? '')) {
                return self::COMMANDS_KEY.':'.$runner['host_id'];
            }
        }

        return self::COMMANDS_KEY;
    }

    private function redis(): Connection
    {
        return Redis::connection((string) config('runners.redis.connection', 'default'));
    }
}
