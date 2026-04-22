<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent;

use App\Models\Tenant\Agent\AgentDeployment;

final class ShowAgentDeployment
{
    public function __invoke(AgentDeployment $deployment): AgentDeployment
    {
        return $deployment->loadMissing('agent');
    }
}
