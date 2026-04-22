<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent;

use App\Data\Tenant\Agent\UpdateAgentDeploymentData;
use App\Models\Tenant\Agent\AgentDeployment;
use Illuminate\Support\Facades\DB;

final class UpdateAgentDeployment
{
    public function __invoke(AgentDeployment $deployment, UpdateAgentDeploymentData $data): AgentDeployment
    {
        return DB::transaction(function () use ($deployment, $data): AgentDeployment {
            if ($data->enabled === true) {
                $deployment->agent->deployments()
                    ->where('channel', $deployment->channel)
                    ->where('id', '!=', $deployment->id)
                    ->update(['enabled' => false]);
            }

            $updateData = array_filter([
                'channel' => $data->channel,
                'enabled' => $data->enabled,
                'config' => $data->config,
                'version' => $data->version,
                'status' => $data->status,
                'metadata' => $data->metadata,
            ], fn ($v) => $v !== null);

            if (($data->enabled ?? false) === true) {
                $updateData['deployed_at'] = now();
            }

            $deployment->update($updateData);

            return $deployment->fresh();
        });
    }
}
