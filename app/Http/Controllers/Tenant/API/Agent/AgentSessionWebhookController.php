<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\Agent;

use App\Events\Tenant\Agent\AgentSessionEvent;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Agent\AgentSession;
use App\Services\Tenant\Agent\Runner\SessionStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgentSessionWebhookController extends Controller
{
    public function __construct(
        private readonly SessionStateService $sessionState,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        if (! $this->isValidApiKey($request->header('X-Tito-Agent-Key'))) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $event = $request->input('event');
        $agentId = $request->input('agent_id');
        $data = $request->input('data', []);
        $sessionId = $data['session_id'] ?? $request->input('session_id', '');

        Log::info("Runner webhook: {$event}", ['session_id' => $sessionId, 'agent_id' => $agentId]);

        match ($event) {
            'session.started' => $this->handleSessionStarted($sessionId, $agentId, $data),
            'session.ended' => $this->handleSessionEnded($sessionId, $agentId, $data),
            'session.transcript' => $this->handleSessionTranscript($sessionId, $data),
            'session.error' => $this->handleSessionError($sessionId, $agentId, $data),
            default => Log::debug("Unhandled runner event: {$event}"),
        };

        return response()->json(['status' => 'received']);
    }

    private function handleSessionStarted(string $sessionId, string $agentId, array $data): void
    {
        AgentSession::updateOrCreate(
            ['external_session_id' => $sessionId],
            [
                'agent_id' => $agentId,
                'channel' => $data['channel'] ?? 'web-widget',
                'status' => 'active',
                'metadata' => [],
                'started_at' => now(),
            ]
        );

        broadcast(new AgentSessionEvent($sessionId, 'session.started', [
            'agent_id' => $agentId,
            'session_id' => $sessionId,
        ]));
    }

    private function handleSessionEnded(string $sessionId, string $agentId, array $data): void
    {
        $session = AgentSession::where('external_session_id', $sessionId)->first();

        if ($session) {
            DB::transaction(function () use ($session, $data): void {
                $session->update([
                    'status' => $data['status'] ?? 'completed',
                    'ended_at' => now(),
                    'metadata' => array_merge($session->metadata ?? [], [
                        'duration_seconds' => $data['duration'] ?? $data['duration_seconds'] ?? null,
                        'termination_reason' => $data['reason'] ?? null,
                        'recording_path' => $data['recording_path'] ?? null,
                    ]),
                ]);

                $transcription = $data['transcription'] ?? [];
                foreach ($transcription as $entry) {
                    if (($entry['role'] ?? '') === 'system') {
                        continue;
                    }

                    $session->transcripts()->create([
                        'role' => $entry['role'] ?? 'user',
                        'content' => $entry['content'] ?? '',
                        'timestamp' => now(),
                    ]);
                }
            });
        }

        $this->sessionState->endSession($sessionId, 'agent', $data);

        broadcast(new AgentSessionEvent($sessionId, 'session.ended', [
            'agent_id' => $agentId,
            'status' => $data['status'] ?? 'completed',
            'duration' => $data['duration'] ?? $data['duration_seconds'] ?? null,
        ]));
    }

    private function handleSessionTranscript(string $sessionId, array $data): void
    {
        $session = AgentSession::where('external_session_id', $sessionId)->first();

        if ($session && ! empty($data['content'] ?? $data['text'] ?? null)) {
            $session->transcripts()->create([
                'role' => $data['role'] ?? 'user',
                'content' => $data['content'] ?? $data['text'] ?? '',
                'timestamp' => isset($data['timestamp']) ? now()->parse($data['timestamp']) : now(),
            ]);
        }

        broadcast(new AgentSessionEvent($sessionId, 'session.transcript', $data));
    }

    private function handleSessionError(string $sessionId, string $agentId, array $data): void
    {
        $session = AgentSession::where('external_session_id', $sessionId)->first();

        if ($session) {
            $session->update([
                'status' => 'failed',
                'ended_at' => now(),
                'metadata' => array_merge($session->metadata ?? [], [
                    'error' => $data['error'] ?? 'Unknown error',
                ]),
            ]);
        }

        $this->sessionState->endSession($sessionId, 'error', $data);

        Log::error('Runner session error', ['session_id' => $sessionId, 'error' => $data['error'] ?? null]);

        broadcast(new AgentSessionEvent($sessionId, 'session.error', [
            'agent_id' => $agentId,
            'error' => $data['error'] ?? 'Unknown error',
        ]));
    }

    private function isValidApiKey(?string $apiKey): bool
    {
        $expectedKey = config('runners.api_key');

        return empty($expectedKey) || $apiKey === $expectedKey;
    }
}
