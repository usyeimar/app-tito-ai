<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent;

use App\Models\Tenant\Agent\AgentDeployment;

final class DeleteAgentDeployment
{
    public function __invoke(AgentDeployment $deployment): void
    {
        $deployment->delete();
    }
}
