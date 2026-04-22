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
        public string $name,
        public string $mode,
        public string $status,
        public ?string $host,
        public ?int $port,
        public ?string $transport,
        public ?array $codecs,
        public ?array $inbound_auth,
        public ?array $outbound_config,
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
            name: $trunk->name,
            mode: $trunk->mode,
            status: $trunk->status,
            host: $trunk->sip_host,
            port: $trunk->sip_port,
            transport: null,
            codecs: $trunk->codecs,
            inbound_auth: $trunk->inbound_auth,
            outbound_config: $trunk->outbound,
            register_config: $trunk->register_config,
            routes: $trunk->routes,
            max_concurrent_calls: $trunk->max_concurrent_calls,
            created_at: $trunk->created_at?->toIso8601String(),
            updated_at: $trunk->updated_at?->toIso8601String(),
        );
    }
}
