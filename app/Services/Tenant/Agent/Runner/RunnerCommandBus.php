<?php

declare(strict_types=1);

namespace App\Services\Tenant\Agent\Runner;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Dispatches async fire-and-forget commands to runners via Redis.
 *
 * Used for operations that don't need a response (terminate, etc.).
 * Session creation uses HTTP directly — see RunnerClient.
 */
class RunnerCommandBus
{
    private const COMMANDS_KEY = 'runner:commands';

    public function __construct(
        private readonly ?RunnerRegistry $runnerRegistry = null,
    ) {}

    /**
     * Dispatch an async command (fire-and-forget).
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $command, array $payload): void
    {
        $message = json_encode([
            'request_id' => Str::ulid()->toString(),
            'command' => $command,
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
        ]);

        $key = $this->resolveCommandsKey($payload['host_id'] ?? null);

        $this->redis()->lpush($key, [$message]);

        Log::debug('Runner command dispatched', [
            'command' => $command,
            'channel' => $key,
        ]);
    }

    /**
     * Route to host-specific key if host_id is provided,
     * or use registry for load balancing, or shared key.
     */
    private function resolveCommandsKey(?string $hostId = null): string
    {
        if ($hostId) {
            return self::COMMANDS_KEY.':'.$hostId;
        }

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
