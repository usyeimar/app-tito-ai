<?php

declare(strict_types=1);

namespace App\Data\Tenant\Agent;

use Spatie\LaravelData\Data;

class CreateAgentToolData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public array $parameters = [],
        public bool $is_active = true,
    ) {}
}
