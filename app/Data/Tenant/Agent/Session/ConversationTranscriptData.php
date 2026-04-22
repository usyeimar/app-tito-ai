<?php

declare(strict_types=1);

namespace App\Data\Tenant\Agent\Session;

use App\Models\Tenant\Agent\AgentSessionTranscript;
use Spatie\LaravelData\Data;

class ConversationTranscriptData extends Data
{
    public function __construct(
        public string $id,
        public string $role,
        public string $content,
        public ?string $timestamp,
    ) {}

    public static function fromTranscript(AgentSessionTranscript $transcript): self
    {
        return new self(
            id: $transcript->id,
            role: $transcript->role,
            content: $transcript->content,
            timestamp: $transcript->timestamp?->toIso8601String(),
        );
    }
}
