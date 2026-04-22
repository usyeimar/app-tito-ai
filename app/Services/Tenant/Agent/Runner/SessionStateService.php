<?php

declare(strict_types=1);

namespace App\Services\Tenant\Agent\Runner;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SessionStateService
{
    private const CACHE_PREFIX = 'agent_session:';

    private const DEFAULT_TTL = 3600;

    public function createSession(
        string $sessionId,
        string $tenantId,
        string $agentId,
        string $roomName,
    ): void {
        Cache::put(self::CACHE_PREFIX.$sessionId, [
            'session_id' => $sessionId,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'room_name' => $roomName,
            'status' => 'active',
            'started_at' => now()->toISOString(),
            'ended_at' => null,
            'ended_by' => null,
            'data' => [],
        ], self::DEFAULT_TTL);

        Log::debug('Agent session registered', ['session_id' => $sessionId]);
    }

    public function endSession(string $sessionId, string $endedBy = 'agent', ?array $data = null): void
    {
        $session = $this->getSession($sessionId);

        if (! $session) {
            Log::warning('Attempted to end unknown session', ['session_id' => $sessionId]);

            return;
        }

        $session['status'] = 'ended';
        $session['ended_at'] = now()->toISOString();
        $session['ended_by'] = $endedBy;
        if ($data) {
            $session['data'] = array_merge($session['data'], $data);
        }

        Cache::put(self::CACHE_PREFIX.$sessionId, $session, self::DEFAULT_TTL);
    }

    public function getSession(string $sessionId): ?array
    {
        return Cache::get(self::CACHE_PREFIX.$sessionId);
    }

    public function isActive(string $sessionId): bool
    {
        $session = $this->getSession($sessionId);

        return $session !== null && $session['status'] === 'active';
    }

    public function deleteSession(string $sessionId): void
    {
        Cache::forget(self::CACHE_PREFIX.$sessionId);
    }
}
