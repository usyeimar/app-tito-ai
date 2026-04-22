<?php

declare(strict_types=1);

namespace App\Data\Tenant\Agent;

use App\Models\Tenant\Agent\AgentTool;
use Spatie\LaravelData\Data;

class AgentToolData extends Data
{
    public function __construct(
        public string $id,
        public string $agent_id,
        public string $name,
        public ?string $description,
        public array $parameters,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
        /** @var array<string, array{href: string, method: string}> */
        public array $_links = [],
    ) {}

    public static function fromTool(AgentTool $tool, ?string $baseUrl = null): self
    {
        $links = [];

        if ($baseUrl !== null) {
            $toolUrl = "{$baseUrl}/{$tool->id}";
            $links = [
                'self' => ['href' => $toolUrl, 'method' => 'GET'],
                'update' => ['href' => $toolUrl, 'method' => 'PATCH'],
                'delete' => ['href' => $toolUrl, 'method' => 'DELETE'],
                'agent' => ['href' => str_replace('/tools', '', $baseUrl), 'method' => 'GET'],
            ];
        }

        return new self(
            id: (string) $tool->id,
            agent_id: (string) $tool->agent_id,
            name: $tool->name,
            description: $tool->description,
            parameters: $tool->parameters ?? [],
            is_active: $tool->is_active,
            created_at: $tool->created_at?->toIso8601String() ?? '',
            updated_at: $tool->updated_at?->toIso8601String() ?? '',
            _links: $links,
        );
    }
}
