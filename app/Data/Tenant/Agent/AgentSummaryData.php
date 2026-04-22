<?php

declare(strict_types=1);

namespace App\Data\Tenant\Agent;

use App\Models\Tenant\Agent\Agent;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

class AgentSummaryData extends Data
{
    /**
     * @param  array<string, string>  $_links
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public string $language,
        public ?array $tags,
        public bool $has_knowledge_base,
        public int $deployments_count,
        public int $tools_count,
        public ?string $created_at,
        public ?string $updated_at,
        public array $_links,
    ) {}

    public static function fromAgent(Agent $agent, string $baseUrl): self
    {
        $agentUrl = "{$baseUrl}/{$agent->id}";

        return new self(
            id: $agent->id,
            name: $agent->name,
            slug: $agent->slug,
            description: $agent->description ? Str::limit($agent->description, 100) : null,
            language: $agent->language,
            tags: $agent->tags,
            has_knowledge_base: $agent->knowledge_base_id !== null,
            deployments_count: (int) ($agent->deployments_count ?? 0),
            tools_count: (int) ($agent->tools_count ?? 0),
            created_at: $agent->created_at?->toIso8601String(),
            updated_at: $agent->updated_at?->toIso8601String(),
            _links: [
                'self' => $agentUrl,
                'update' => $agentUrl,
                'delete' => $agentUrl,
                'deployments' => "{$agentUrl}/deployments",
                'tools' => "{$agentUrl}/tools",
                'conversations' => "{$agentUrl}/conversations",
                'duplicate' => "{$agentUrl}/duplicate",
            ],
        );
    }
}
