<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent;

use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\AgentSetting;
use App\Models\Tenant\Agent\AgentTool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DuplicateAgent
{
    public function __invoke(Agent $source, ?string $name = null): Agent
    {
        return DB::transaction(function () use ($source, $name): Agent {
            $source->loadMissing(['settings', 'tools']);

            $newName = $name ?? $source->name.' (Copy)';

            $agent = Agent::create([
                'name' => $newName,
                'slug' => Str::slug($newName).'-'.Str::random(4),
                'description' => $source->description,
                'language' => $source->language,
                'tags' => $source->tags ?? [],
                'timezone' => $source->timezone,
                'currency' => $source->currency,
                'number_format' => $source->number_format,
                'knowledge_base_id' => $source->knowledge_base_id,
            ]);

            if ($source->settings) {
                AgentSetting::create([
                    'agent_id' => $agent->id,
                    'brain_config' => $source->settings->brain_config,
                    'runtime_config' => $source->settings->runtime_config,
                    'architecture_config' => $source->settings->architecture_config,
                    'capabilities_config' => $source->settings->capabilities_config,
                    'observability_config' => $source->settings->observability_config,
                ]);
            }

            foreach ($source->tools as $tool) {
                AgentTool::create([
                    'agent_id' => $agent->id,
                    'name' => $tool->name,
                    'type' => $tool->type,
                    'api_endpoint' => $tool->api_endpoint,
                    'requires_confirmation' => $tool->requires_confirmation,
                    'timeout_ms' => $tool->timeout_ms,
                    'disabled' => $tool->disabled,
                ]);
            }

            return $agent->load('settings');
        });
    }
}
