<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent;

use App\Models\Tenant\Agent\AgentTool;

final class DeleteAgentTool
{
    public function __invoke(AgentTool $tool): void
    {
        $tool->delete();
    }
}
