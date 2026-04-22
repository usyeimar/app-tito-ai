<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent;

use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\AgentTool;
use Illuminate\Database\Eloquent\Collection;

final class ListAgentTools
{
    /** @return Collection<int, AgentTool> */
    public function __invoke(Agent $agent): Collection
    {
        return $agent->tools()->orderBy('name')->get();
    }
}
