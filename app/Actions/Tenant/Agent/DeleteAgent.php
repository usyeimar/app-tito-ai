<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent;

use App\Models\Tenant\Agent\Agent;
use App\Services\Tenant\Agent\Runner\AgentRedisSyncService;
use Illuminate\Support\Facades\Log;

final class DeleteAgent
{
    public function __construct(
        private readonly AgentRedisSyncService $redisSync,
    ) {}

    public function __invoke(Agent $agent): void
    {
        $agent->delete();

        // Remove agent from Redis cache so the SIP bridge no longer resolves it
        try {
            $this->redisSync->remove($agent);
        } catch (\Throwable $e) {
            Log::warning('Failed to remove agent from Redis on delete', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
