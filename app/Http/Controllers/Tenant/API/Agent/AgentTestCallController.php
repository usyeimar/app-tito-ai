<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\Agent;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\AgentSession;
use App\Services\Tenant\Agent\Runner\RunnerClient;
use App\Services\Tenant\Agent\Runner\SessionStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgentTestCallController extends Controller
{
    /** @var string[] Transports the WebRTC UI can render */
    private const SUPPORTED_TRANSPORTS = ['livekit', 'daily'];

    public function __construct(
        private readonly RunnerClient $runner,
        private readonly SessionStateService $sessionState,
    ) {}

    public function start(Request $request, Agent $agent): JsonResponse
    {
        Gate::authorize('view', $agent);

        $variables = $request->input('variables', []);

        $session = $this->runner->createSession($agent, $variables);
        $provider = $session['provider'] ?? 'unknown';

        if (! in_array($provider, self::SUPPORTED_TRANSPORTS, true)) {
            if (! empty($session['session_id'])) {
                $this->runner->terminateSession((string) $session['session_id']);
            }

            abort(503, "Unsupported transport: {$provider}. Supported: ".implode(', ', self::SUPPORTED_TRANSPORTS));
        }

        $this->sessionState->createSession(
            sessionId: $session['session_id'],
            tenantId: (string) (tenant('id') ?? 'central'),
            agentId: (string) $agent->id,
            roomName: $session['room_name'],
        );

        $runnerWsUrl = rtrim(config('app.url'), '/').'/runner';

        return response()->json([
            'success' => true,
            'data' => array_merge($session, [
                'runner_ws_url' => $runnerWsUrl,
            ]),
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

    public function transcripts(Request $request, string $channelId): JsonResponse
    {
        $session = $this->sessionState->getSession($channelId);

        if (! $session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $agentSession = AgentSession::where('external_session_id', $channelId)
            ->with(['transcripts' => fn ($q) => $q->orderBy('timestamp')])
            ->first();

        if (! $agentSession) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $agentSession->transcripts->map(fn ($t) => [
                'role' => $t->role,
                'content' => $t->content,
                'timestamp' => $t->timestamp?->toIso8601String(),
            ]),
        ]);
    }

    public function stop(Request $request, Agent $agent, string $session): JsonResponse
    {
        Gate::authorize('view', $agent);

        $ok = $this->runner->terminateSession($session);

        return response()->json(['data' => ['terminated' => $ok]]);
    }
}
