<?php

declare(strict_types=1);

namespace App\Services\Tenant\Agent\Runner;

use App\Models\Tenant\Agent\AgentSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SessionStateService
{
    private const CACHE_PREFIX = 'agent_session:';

    private const DEFAULT_TTL = 3600;

    private const ACTIVE_TTL = 7200; // 2h for active sessions

    public function createSession(
        string $sessionId,
        string $tenantId,
        string $agentId,
        string $roomName,
    ): void {
        $state = [
            'session_id' => $sessionId,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'room_name' => $roomName,
            'status' => 'active',
            'started_at' => now()->toISOString(),
            'ended_at' => null,
            'ended_by' => null,
            'data' => [],
        ];

        Cache::put(self::CACHE_PREFIX.$sessionId, $state, self::ACTIVE_TTL);

        // Persist to DB as source of truth
        AgentSession::updateOrCreate(
            ['external_session_id' => $sessionId],
            [
                'agent_id' => $agentId,
                'status' => 'active',
                'metadata' => ['tenant_id' => $tenantId, 'room_name' => $roomName],
                'started_at' => now(),
            ]
        );

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

        // Keep ended sessions in cache briefly for status polling
        Cache::put(self::CACHE_PREFIX.$sessionId, $session, self::DEFAULT_TTL);
    }

    public function getSession(string $sessionId): ?array
    {
        return Cache::get(self::CACHE_PREFIX.$sessionId);
    }

    /**
     * Extend TTL on each webhook event to prevent active sessions from expiring.
     */
    public function touch(string $sessionId): void
    {
        $session = $this->getSession($sessionId);

        if ($session && $session['status'] === 'active') {
            Cache::put(self::CACHE_PREFIX.$sessionId, $session, self::ACTIVE_TTL);
        }
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

    /**
     * Find and mark orphaned sessions as failed.
     *
     * Called by the reconciliation command to clean up sessions that
     * never received a session.ended webhook.
     */
    public function reconcileOrphanedSessions(int $staleMinutes = 60): int
    {
        $orphaned = AgentSession::where('status', 'active')
            ->where('started_at', '<', now()->subMinutes($staleMinutes))
            ->get();

        foreach ($orphaned as $session) {
            $session->update([
                'status' => 'failed',
                'ended_at' => now(),
                'metadata' => array_merge($session->metadata ?? [], [
                    'termination_reason' => 'reconciliation: no heartbeat',
                ]),
            ]);

            if ($session->external_session_id) {
                $this->endSession($session->external_session_id, 'reconciliation');
            }

            Log::info('Reconciled orphaned session', [
                'session_id' => $session->external_session_id,
                'agent_id' => $session->agent_id,
            ]);
        }

        return $orphaned->count();
    }
}
