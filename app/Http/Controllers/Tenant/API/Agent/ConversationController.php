<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\Agent;

use App\Actions\Tenant\Agent\Conversation\DeleteConversation;
use App\Actions\Tenant\Agent\Conversation\GetConversationTranscripts;
use App\Actions\Tenant\Agent\Conversation\ListConversations;
use App\Actions\Tenant\Agent\Conversation\ShowConversation;
use App\Data\Tenant\Agent\Session\ConversationData;
use App\Data\Tenant\Agent\Session\ConversationTranscriptData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\API\Agent\IndexConversationRequest;
use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\AgentSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function index(IndexConversationRequest $request, ListConversations $action): JsonResponse
    {
        Gate::authorize('viewAny', Agent::class);

        $paginator = $action($request->validated());
        $baseUrl = $this->conversationsBaseUrl();

        $data = collect($paginator->items())
            ->map(fn (AgentSession $s) => ConversationData::fromSession($s, $baseUrl));

        return response()->json([
            'data' => $data,
            '_links' => [
                'self' => ['href' => $paginator->url($paginator->currentPage()), 'method' => 'GET'],
                'first' => ['href' => $paginator->url(1), 'method' => 'GET'],
                'last' => ['href' => $paginator->url($paginator->lastPage()), 'method' => 'GET'],
                'next' => $paginator->nextPageUrl() ? ['href' => $paginator->nextPageUrl(), 'method' => 'GET'] : null,
                'prev' => $paginator->previousPageUrl() ? ['href' => $paginator->previousPageUrl(), 'method' => 'GET'] : null,
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(AgentSession $conversation, ShowConversation $action): JsonResponse
    {
        Gate::authorize('viewAny', Agent::class);

        $session = $action($conversation);
        $baseUrl = $this->conversationsBaseUrl();

        return response()->json([
            'data' => ConversationData::fromSession($session, $baseUrl),
        ]);
    }

    public function transcripts(AgentSession $conversation, GetConversationTranscripts $action): JsonResponse
    {
        Gate::authorize('viewAny', Agent::class);

        $transcripts = $action($conversation);

        return response()->json([
            'data' => $transcripts->map(fn ($t) => ConversationTranscriptData::fromTranscript($t)),
        ]);
    }

    public function destroy(AgentSession $conversation, DeleteConversation $action): Response
    {
        Gate::authorize('viewAny', Agent::class);

        $action($conversation);

        return response()->noContent();
    }

    private function conversationsBaseUrl(): string
    {
        $tenantSlug = tenant()?->slug ?? '';

        return "/{$tenantSlug}/api/ai/conversations";
    }
}
