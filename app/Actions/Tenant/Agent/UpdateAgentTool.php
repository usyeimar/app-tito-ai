<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent;

use App\Data\Tenant\Agent\UpdateAgentToolData;
use App\Models\Tenant\Agent\AgentTool;

final class UpdateAgentTool
{
    public function __invoke(AgentTool $tool, UpdateAgentToolData $data): AgentTool
    {
        $tool->update(array_filter([
            'name' => $data->name,
            'description' => $data->description,
            'parameters' => $data->parameters,
            'is_active' => $data->is_active,
        ], fn ($v) => $v !== null));

        return $tool->fresh();
    }
}
