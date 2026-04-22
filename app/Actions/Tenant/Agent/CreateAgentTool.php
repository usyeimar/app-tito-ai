<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent;

use App\Data\Tenant\Agent\CreateAgentToolData;
use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\AgentTool;

final class CreateAgentTool
{
    public function __invoke(Agent $agent, CreateAgentToolData $data): AgentTool
    {
        return $agent->tools()->create([
            'name' => $data->name,
            'description' => $data->description,
            'parameters' => $data->parameters,
            'is_active' => $data->is_active,
        ]);
    }
}
