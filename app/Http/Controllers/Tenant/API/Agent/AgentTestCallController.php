<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\Agent;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Agent\Agent;
use App\Services\Tenant\Agent\Runner\RunnerClient;
use App\Services\Tenant\Agent\Runner\SessionStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgentTestCallController extends Controller
{
    public function __construct(
        private readonly RunnerClient $runner,
        private readonly SessionStateService $sessionState,
    ) {}

    public function start(Request $request, Agent $agent): JsonResponse
    {
        Gate::authorize('view', $agent);

        $session = $this->runner->createSession($agent);

        if (($session['provider'] ?? null) !== 'livekit') {
            if (! empty($session['session_id'])) {
                $this->runner->terminateSession((string) $session['session_id']);
            }

            abort(503, 'El runner devolvió un transporte no soportado: '
                .($session['provider'] ?? 'unknown').'. Esta UI solo soporta LiveKit.');
        }

        $this->sessionState->createSession(
            sessionId: $session['session_id'],
            tenantId: (string) (tenant('id') ?? 'central'),
            agentId: (string) $agent->id,
            roomName: $session['room_name'],
        );

        return response()->json([
            'data' => $session,
            'message' => 'Test session created.',
        ], 201);
    }

    public function status(Request $request, string $sessionId): JsonResponse
    {
        $session = $this->sessionState->getSession($sessionId);

        if (! $session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        return response()->json(['data' => $session]);
    }

    public function userEnded(Request $request, string $sessionId): JsonResponse
    {
        $session = $this->sessionState->getSession($sessionId);

        if (! $session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $this->sessionState->endSession($sessionId, 'user');

        return response()->json(['message' => 'Session marked as ended by user']);
    }

    public function stop(Request $request, Agent $agent, string $session): JsonResponse
    {
        Gate::authorize('view', $agent);

        $ok = $this->runner->terminateSession($session);

        return response()->json(['data' => ['terminated' => $ok]]);
    }
}
