<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent;

use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\AgentDeployment;
use Illuminate\Database\Eloquent\Collection;

final class ListAgentDeployments
{
    /** @return Collection<int, AgentDeployment> */
    public function __invoke(Agent $agent): Collection
    {
        return $agent->deployments()->orderByDesc('enabled')->orderBy('channel')->get();
    }
}
