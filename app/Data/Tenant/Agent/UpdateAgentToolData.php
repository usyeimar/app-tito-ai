<?php

declare(strict_types=1);

namespace App\Data\Tenant\Agent;

use Spatie\LaravelData\Data;

class UpdateAgentToolData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?array $parameters = null,
        public ?bool $is_active = null,
    ) {}
}
