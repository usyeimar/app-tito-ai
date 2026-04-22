<?php

declare(strict_types=1);

namespace App\Data\Tenant\Agent\Session;

use Spatie\LaravelData\Data;

class CreateAgentSessionTranscriptData extends Data
{
    public function __construct(
        public string $role,
        public string $content,
        public ?string $timestamp = null,
    ) {}
}
