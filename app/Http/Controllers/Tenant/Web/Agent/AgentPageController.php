<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Web\Agent;

use App\Actions\Tenant\Agent\CreateAgent;
use App\Actions\Tenant\Agent\ListAgents;
use App\Actions\Tenant\Agent\ShowAgent;
use App\Data\Tenant\Agent\AgentSummaryData;
use App\Data\Tenant\Agent\CreateAgentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\API\Agent\StoreAgentRequest;
use App\Models\Tenant\Agent\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Stancl\Tenancy\Contracts\Tenant;

class AgentPageController extends Controller
{
    public function index(ListAgents $listAction): Response
    {
        Gate::authorize('viewAny', Agent::class);

        return Inertia::render('tenant/agents/show', [
            'tenant' => $this->tenantPayload(),
            'agent' => null,
            'agents' => Inertia::defer(fn () => $listAction()
                ->through(fn (Agent $a) => AgentSummaryData::fromAgent($a, '')->toArray())
                ->items()),
        ]);
    }

    public function show(Agent $agent, ShowAgent $showAction, ListAgents $listAction): Response
    {
        Gate::authorize('view', $agent);

        return Inertia::render('tenant/agents/show', [
            'tenant' => $this->tenantPayload(),
            'agent' => $showAction($agent)->toArray(),
            'agents' => Inertia::defer(fn () => $listAction()
                ->through(fn (Agent $a) => AgentSummaryData::fromAgent($a, '')->toArray())
                ->items()),
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

    /**
     * @return array{id: string, name: string, slug: string}|null
     */
    private function tenantPayload(): ?array
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if ($tenant === null) {
            return null;
        }

        return [
            'id' => (string) $tenant->getTenantKey(),
            'name' => (string) ($tenant->name ?? $tenant->getTenantKey()),
            'slug' => (string) ($tenant->slug ?? $tenant->getTenantKey()),
        ];
    }
}
