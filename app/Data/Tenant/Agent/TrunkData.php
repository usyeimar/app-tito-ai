<?php

declare(strict_types=1);

namespace App\Data\Tenant\Agent;

use App\Models\Tenant\Agent\Trunk;
use Spatie\LaravelData\Data;

class TrunkData extends Data
{
    public function __construct(
        public string $id,
        public ?string $agent_id,
        public ?string $workspace_slug,
        public string $name,
        public string $mode,
        public string $status,
        public ?string $sip_host,
        public ?int $sip_port,
        public ?array $codecs,
        public ?array $inbound_auth,
        public ?array $outbound,
        public ?array $register_config,
        public ?array $routes,
        public int $max_concurrent_calls,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromTrunk(Trunk $trunk): self
    {
        return new self(
            id: $trunk->id,
            agent_id: $trunk->agent_id,
            workspace_slug: tenant()?->slug,
            name: $trunk->name,
            mode: $trunk->mode,
            status: $trunk->status,
            sip_host: $trunk->sip_host,
            sip_port: $trunk->sip_port,
            codecs: $trunk->codecs,
            inbound_auth: $trunk->inbound_auth,
            outbound: $trunk->outbound,
            register_config: $trunk->register_config,
            routes: $trunk->routes,
            max_concurrent_calls: $trunk->max_concurrent_calls,
            created_at: $trunk->created_at?->toIso8601String(),
            updated_at: $trunk->updated_at?->toIso8601String(),
        );
    }
}
