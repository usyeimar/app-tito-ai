<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\Agent;

use App\Actions\Tenant\Agent\CreateAgentTool;
use App\Actions\Tenant\Agent\DeleteAgentTool;
use App\Actions\Tenant\Agent\ListAgentTools;
use App\Actions\Tenant\Agent\UpdateAgentTool;
use App\Data\Tenant\Agent\AgentToolData;
use App\Data\Tenant\Agent\CreateAgentToolData;
use App\Data\Tenant\Agent\UpdateAgentToolData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\API\Agent\StoreAgentToolRequest;
use App\Http\Requests\Tenant\API\Agent\UpdateAgentToolRequest;
use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\AgentTool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AgentToolController extends Controller
{
    public function index(Agent $agent, ListAgentTools $action): JsonResponse
    {
        Gate::authorize('view', $agent);

        $baseUrl = $this->toolsBaseUrl($agent);
        $tools = $action($agent);

        return response()->json([
            'data' => $tools->map(fn (AgentTool $tool) => AgentToolData::fromTool($tool, $baseUrl)),
            '_links' => [
                'self' => ['href' => $baseUrl, 'method' => 'GET'],
                'create' => ['href' => $baseUrl, 'method' => 'POST'],
                'agent' => ['href' => $this->agentUrl($agent), 'method' => 'GET'],
            ],
        ]);
    }

    public function store(StoreAgentToolRequest $request, Agent $agent, CreateAgentTool $action): JsonResponse
    {
        Gate::authorize('update', $agent);

        $data = CreateAgentToolData::from($request->validated());
        $tool = $action($agent, $data);

        return response()->json([
            'data' => AgentToolData::fromTool($tool, $this->toolsBaseUrl($agent)),
            'message' => 'Tool created.',
            '_links' => [
                'self' => ['href' => $this->toolUrl($agent, $tool), 'method' => 'GET'],
                'collection' => ['href' => $this->toolsBaseUrl($agent), 'method' => 'GET'],
            ],
        ], 201);
    }

    public function show(Agent $agent, AgentTool $tool): JsonResponse
    {
        Gate::authorize('view', $agent);

        return response()->json([
            'data' => AgentToolData::fromTool($tool, $this->toolsBaseUrl($agent)),
        ]);
    }

    public function update(
        UpdateAgentToolRequest $request,
        Agent $agent,
        AgentTool $tool,
        UpdateAgentTool $action,
    ): JsonResponse {
        Gate::authorize('update', $agent);

        $data = UpdateAgentToolData::from($request->validated());
        $tool = $action($tool, $data);

        return response()->json([
            'data' => AgentToolData::fromTool($tool, $this->toolsBaseUrl($agent)),
            'message' => 'Tool updated.',
        ]);
    }

    public function destroy(Agent $agent, AgentTool $tool, DeleteAgentTool $action): Response
    {
        Gate::authorize('update', $agent);

        $action($tool);

        return response()->noContent();
    }

    private function toolsBaseUrl(Agent $agent): string
    {
        $tenantSlug = tenant()?->slug ?? '';

        return "/{$tenantSlug}/api/ai/agents/{$agent->id}/tools";
    }

    private function toolUrl(Agent $agent, AgentTool $tool): string
    {
        return $this->toolsBaseUrl($agent).'/'.$tool->id;
    }

    private function agentUrl(Agent $agent): string
    {
        $tenantSlug = tenant()?->slug ?? '';

        return "/{$tenantSlug}/api/ai/agents/{$agent->id}";
    }
}
