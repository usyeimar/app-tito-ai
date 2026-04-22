<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\Agent;

use App\Actions\Tenant\Agent\Session\UploadSessionAudio;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Agent\AgentSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentSessionAudioController extends Controller
{
    public function store(Request $request, string $sessionId, UploadSessionAudio $action): JsonResponse
    {
        $apiKey = $request->header('X-Tito-Agent-Key');
        $expectedKey = config('runners.api_key');

        if (! empty($expectedKey) && $apiKey !== $expectedKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'audio' => ['required', 'file', 'max:102400'],
        ]);

        $session = AgentSession::where('external_session_id', $sessionId)->first();

        if (! $session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $audio = $action($session, $request->file('audio'));

        return response()->json([
            'data' => [
                'id' => $audio->id,
                'name' => $audio->name,
                'size' => $audio->size,
            ],
            'message' => 'Audio uploaded.',
        ], 201);
    }
}
