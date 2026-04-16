<?php

namespace App\Data\Tenant\Agent\Session;

use Spatie\LaravelData\Data;

class AgentSessionData extends Data
{
    public function __construct(
        public string $id,
        public string $agent_id,
        public string $status,
        public string $started_at,
        public ?string $ended_at,
    ) {}
}
