<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\Agent;

use App\Actions\Tenant\Agent\CreateAgentDeployment;
use App\Actions\Tenant\Agent\DeleteAgentDeployment;
use App\Actions\Tenant\Agent\ListAgentDeployments;
use App\Actions\Tenant\Agent\ShowAgentDeployment;
use App\Actions\Tenant\Agent\UpdateAgentDeployment;
use App\Data\Tenant\Agent\AgentDeploymentData;
use App\Data\Tenant\Agent\CreateAgentDeploymentData;
use App\Data\Tenant\Agent\UpdateAgentDeploymentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\API\Agent\StoreAgentDeploymentRequest;
use App\Http\Requests\Tenant\API\Agent\UpdateAgentDeploymentRequest;
use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\AgentDeployment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AgentDeploymentController extends Controller
{
    public function index(Agent $agent, ListAgentDeployments $action): JsonResponse
    {
        Gate::authorize('view', $agent);

        $baseUrl = $this->deploymentsBaseUrl($agent);
        $deployments = $action($agent);

        return response()->json([
            'data' => $deployments->map(fn (AgentDeployment $d) => AgentDeploymentData::fromDeployment($d, $baseUrl)),
            '_links' => [
                'self' => ['href' => $baseUrl, 'method' => 'GET'],
                'create' => ['href' => $baseUrl, 'method' => 'POST'],
                'agent' => ['href' => $this->agentUrl($agent), 'method' => 'GET'],
            ],
        ]);
    }

    public function store(StoreAgentDeploymentRequest $request, Agent $agent, CreateAgentDeployment $action): JsonResponse
    {
        Gate::authorize('update', $agent);

        $data = CreateAgentDeploymentData::from($request->validated());
        $deployment = $action($agent, $data);
        $deployment->loadMissing('agent');

        return response()->json([
            'data' => AgentDeploymentData::fromDeployment($deployment, $this->deploymentsBaseUrl($agent)),
            'message' => 'Deployment created.',
            '_links' => [
                'self' => ['href' => $this->deploymentUrl($agent, $deployment), 'method' => 'GET'],
                'collection' => ['href' => $this->deploymentsBaseUrl($agent), 'method' => 'GET'],
            ],
        ], 201);
    }

    public function show(Agent $agent, AgentDeployment $deployment, ShowAgentDeployment $action): JsonResponse
    {
        Gate::authorize('view', $agent);

        $deployment = $action($deployment);

        return response()->json([
            'data' => AgentDeploymentData::fromDeployment($deployment, $this->deploymentsBaseUrl($agent)),
        ]);
    }

    public function update(
        UpdateAgentDeploymentRequest $request,
        Agent $agent,
        AgentDeployment $deployment,
        UpdateAgentDeployment $action,
    ): JsonResponse {
        Gate::authorize('update', $agent);

        $data = UpdateAgentDeploymentData::from($request->validated());
        $deployment = $action($deployment, $data);
        $deployment->loadMissing('agent');

        return response()->json([
            'data' => AgentDeploymentData::fromDeployment($deployment, $this->deploymentsBaseUrl($agent)),
            'message' => 'Deployment updated.',
        ]);
    }

    public function destroy(Agent $agent, AgentDeployment $deployment, DeleteAgentDeployment $action): Response
    {
        Gate::authorize('update', $agent);

        $action($deployment);

        return response()->noContent();
    }

    private function deploymentsBaseUrl(Agent $agent): string
    {
        $tenantSlug = tenant()?->slug ?? '';

        return "/{$tenantSlug}/api/ai/agents/{$agent->id}/deployments";
    }

    private function deploymentUrl(Agent $agent, AgentDeployment $deployment): string
    {
        return $this->deploymentsBaseUrl($agent).'/'.$deployment->id;
    }

    private function agentUrl(Agent $agent): string
    {
        $tenantSlug = tenant()?->slug ?? '';

        return "/{$tenantSlug}/api/ai/agents/{$agent->id}";
    }
}
