<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent;

use App\Data\Tenant\Agent\CreateAgentDeploymentData;
use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\AgentDeployment;
use Illuminate\Support\Facades\DB;

final class CreateAgentDeployment
{
    public function __invoke(Agent $agent, CreateAgentDeploymentData $data): AgentDeployment
    {
        return DB::transaction(function () use ($agent, $data): AgentDeployment {
            if ($data->enabled) {
                $agent->deployments()
                    ->where('channel', $data->channel)
                    ->update(['enabled' => false]);
            }

            return $agent->deployments()->create([
                'channel' => $data->channel,
                'enabled' => $data->enabled,
                'config' => $data->config,
                'version' => $data->version,
                'deployed_at' => $data->enabled ? now() : null,
                'status' => $data->status,
                'metadata' => $data->metadata,
            ]);
        });
    }
}
