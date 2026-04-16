<?php

namespace App\Data\Tenant\Agent\Session;

use Spatie\LaravelData\Data;

class AgentSessionAudioData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $path,
        public string $mime_type,
        public int $size,
    ) {}
}
