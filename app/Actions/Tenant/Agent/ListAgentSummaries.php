<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent;

use App\Data\Tenant\Agent\AgentSummaryData;
use App\Models\Tenant\Agent\Agent;
use Illuminate\Support\Collection;

final class ListAgentSummaries
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, AgentSummaryData>
     */
    public function __invoke(array $filters, string $baseUrl): Collection
    {
        $query = Agent::withCount(['deployments', 'tools'])->orderBy('name');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $query->get()->map(fn (Agent $agent) => AgentSummaryData::fromAgent($agent, $baseUrl));
    }
}
