<?php

declare(strict_types=1);

namespace App\Services\Tenant\Agent;

use App\Models\Tenant\Agent\Agent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AgentService
{
    public function getAll(string $tenantId): Collection
    {
        return Agent::with(['settings', 'tools'])
            ->orderBy('name')
            ->get();
    }

    public function findById(string $id): ?Agent
    {
        return Agent::with(['settings', 'tools'])
            ->where('id', $id)
            ->first();
    }

    public function create(array $data): Agent
    {
        return DB::transaction(function () use ($data) {
            $agent = Agent::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? null,
                'description' => $data['description'] ?? null,
                'language' => $data['language'] ?? 'es-CO',
                'tags' => $data['tags'] ?? [],
                'timezone' => $data['timezone'] ?? 'UTC',
                'currency' => $data['currency'] ?? 'COP',
                'number_format' => $data['number_format'] ?? 'es_CO',
                'knowledge_base_id' => $data['knowledge_base_id'] ?? null,
            ]);

            $agent->settings()->create([
                'brain_config' => $data['brain_config'] ?? [],
                'runtime_config' => $data['runtime_config'] ?? [],
                'architecture_config' => $data['architecture_config'] ?? [],
                'capabilities_config' => $data['capabilities_config'] ?? [],
                'observability_config' => $data['observability_config'] ?? [],
            ]);

            return $agent->load(['settings', 'tools']);
        });
    }

    public function update(Agent $agent, array $data): Agent
    {
        return DB::transaction(function () use ($agent, $data) {
            $agent->update(array_filter([
                'name' => $data['name'] ?? null,
                'slug' => $data['slug'] ?? null,
                'description' => $data['description'] ?? null,
                'language' => $data['language'] ?? null,
                'tags' => $data['tags'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'currency' => $data['currency'] ?? null,
                'number_format' => $data['number_format'] ?? null,
                'knowledge_base_id' => $data['knowledge_base_id'] ?? null,
            ]));

            $settingsData = array_filter([
                'brain_config' => $data['brain_config'] ?? null,
                'runtime_config' => $data['runtime_config'] ?? null,
                'architecture_config' => $data['architecture_config'] ?? null,
                'capabilities_config' => $data['capabilities_config'] ?? null,
                'observability_config' => $data['observability_config'] ?? null,
            ]);

            if (! empty($settingsData)) {
                $agent->settings()->updateOrCreate(
                    ['agent_id' => $agent->id],
                    $settingsData
                );
            }

            return $agent->fresh(['settings', 'tools']);
        });
    }

    public function delete(Agent $agent): void
    {
        $agent->delete();
    }
}
