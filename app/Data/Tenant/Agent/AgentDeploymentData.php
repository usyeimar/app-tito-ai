<?php

declare(strict_types=1);

namespace App\Data\Tenant\Agent;

use App\Enums\DeploymentChannel;
use App\Models\Tenant\Agent\AgentDeployment;
use Spatie\LaravelData\Data;

class AgentDeploymentData extends Data
{
    public function __construct(
        public string $id,
        public string $agent_id,
        public string $channel,
        public string $channel_label,
        public bool $enabled,
        public ?array $config,
        public string $version,
        public ?string $status,
        public ?array $metadata,
        public ?string $deployed_at,
        public string $created_at,
        public string $updated_at,
        /** @var array<string, array{href: string, method: string}> */
        public array $_links = [],
    ) {}

    public static function fromDeployment(AgentDeployment $deployment, ?string $baseUrl = null): self
    {
        $links = [];

        if ($baseUrl !== null) {
            $deploymentUrl = "{$baseUrl}/{$deployment->id}";
            $links = [
                'self' => ['href' => $deploymentUrl, 'method' => 'GET'],
                'update' => ['href' => $deploymentUrl, 'method' => 'PATCH'],
                'delete' => ['href' => $deploymentUrl, 'method' => 'DELETE'],
                'agent' => ['href' => str_replace('/deployments', '', $baseUrl), 'method' => 'GET'],
            ];

            if ($deployment->channel === 'web-widget' && $deployment->enabled) {
                $tenantSlug = tenant()?->slug ?? '';
                $links['widget_config'] = [
                    'href' => "/{$tenantSlug}/api/public/agents/{$deployment->agent?->slug}/widget-config",
                    'method' => 'GET',
                ];
            }
        }

        return new self(
            id: (string) $deployment->id,
            agent_id: (string) $deployment->agent_id,
            channel: $deployment->channel,
            channel_label: DeploymentChannel::tryFrom($deployment->channel)?->label() ?? $deployment->channel,
            enabled: $deployment->enabled,
            config: $deployment->config,
            version: $deployment->version ?? '1.0.0',
            status: $deployment->status,
            metadata: $deployment->metadata,
            deployed_at: $deployment->deployed_at?->toIso8601String(),
            created_at: $deployment->created_at?->toIso8601String() ?? '',
            updated_at: $deployment->updated_at?->toIso8601String() ?? '',
            _links: $links,
        );
    }
}
