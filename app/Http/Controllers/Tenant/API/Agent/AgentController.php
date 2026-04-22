<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\Agent;

use App\Actions\Tenant\Agent\CreateAgent;
use App\Actions\Tenant\Agent\DeleteAgent;
use App\Actions\Tenant\Agent\DuplicateAgent;
use App\Actions\Tenant\Agent\ListAgents;
use App\Actions\Tenant\Agent\ListAgentSummaries;
use App\Actions\Tenant\Agent\ShowAgent;
use App\Actions\Tenant\Agent\UpdateAgent;
use App\Data\Tenant\Agent\AgentData;
use App\Data\Tenant\Agent\CreateAgentData;
use App\Data\Tenant\Agent\UpdateAgentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\API\Agent\IndexAgentRequest;
use App\Http\Requests\Tenant\API\Agent\StoreAgentRequest;
use App\Http\Requests\Tenant\API\Agent\UpdateAgentRequest;
use App\Models\Tenant\Agent\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AgentController extends Controller
{
    public function index(IndexAgentRequest $request, ListAgents $action): JsonResponse
    {
        Gate::authorize('viewAny', Agent::class);

        return response()->json([
            'data' => $action($request->validated())->map->toArray()->values(),
        ]);
    }

    public function store(StoreAgentRequest $request, CreateAgent $action): JsonResponse
    {
        Gate::authorize('create', Agent::class);

        $agentData = $action(CreateAgentData::from($request->validated()));

        return response()->json([
            'data' => $agentData->toArray(),
            'message' => 'Agent created',
        ], 201);
    }

    public function show(Agent $agent, ShowAgent $action): JsonResponse
    {
        Gate::authorize('view', $agent);

        return response()->json([
            'data' => $action($agent)->toArray(),
        ]);
    }

    public function update(UpdateAgentRequest $request, Agent $agent, UpdateAgent $action): JsonResponse
    {
        Gate::authorize('update', $agent);

        $agentData = $action($agent, UpdateAgentData::from($request->validated()));

        return response()->json([
            'data' => $agentData->toArray(),
            'message' => 'Agent updated',
        ]);
    }

    public function destroy(Agent $agent, DeleteAgent $action): Response
    {
        Gate::authorize('delete', $agent);

        $action($agent);

        return response()->noContent();
    }

    public function duplicate(Request $request, Agent $agent, DuplicateAgent $action): JsonResponse
    {
        Gate::authorize('create', Agent::class);

        $newAgent = $action($agent, $request->input('name'));

        return response()->json([
            'data' => AgentData::fromAgent($newAgent)->toArray(),
            'message' => 'Agent duplicated',
        ], 201);
    }

    public function summaries(IndexAgentRequest $request, ListAgentSummaries $action): JsonResponse
    {
        Gate::authorize('viewAny', Agent::class);

        $baseUrl = $request->getSchemeAndHttpHost().'/api/agents';

        return response()->json([
            'data' => $action($request->validated(), $baseUrl)->toArray(),
        ]);
    }
}
