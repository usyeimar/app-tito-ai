<?php

declare(strict_types=1);

namespace App\Data\Tenant\Agent\Session;

use App\Models\Tenant\Agent\AgentSession;
use Spatie\LaravelData\Data;

class ConversationData extends Data
{
    public function __construct(
        public string $id,
        public string $agent_id,
        public ?string $agent_name,
        public ?string $channel,
        public ?string $external_session_id,
        public string $status,
        public ?array $metadata,
        public ?string $started_at,
        public ?string $ended_at,
        public ?int $duration_seconds,
        public int $transcript_count,
        public string $created_at,
        /** @var array<string, array{href: string, method: string}> */
        public array $_links = [],
    ) {}

    public static function fromSession(AgentSession $session, ?string $baseUrl = null): self
    {
        $session->loadMissing('agent');

        $durationSeconds = null;
        if ($session->started_at && $session->ended_at) {
            $durationSeconds = (int) $session->started_at->diffInSeconds($session->ended_at);
        }

        $links = [];
        if ($baseUrl !== null) {
            $selfUrl = "{$baseUrl}/{$session->id}";
            $tenantSlug = tenant()?->slug ?? '';
            $links = [
                'self' => ['href' => $selfUrl, 'method' => 'GET'],
                'agent' => ['href' => "/{$tenantSlug}/api/ai/agents/{$session->agent_id}", 'method' => 'GET'],
                'transcripts' => ['href' => "{$selfUrl}/transcripts", 'method' => 'GET'],
                'audio' => ['href' => "{$selfUrl}/audio", 'method' => 'GET'],
                'delete' => ['href' => $selfUrl, 'method' => 'DELETE'],
            ];
        }

        return new self(
            id: $session->id,
            agent_id: $session->agent_id,
            agent_name: $session->agent?->name,
            channel: $session->channel,
            external_session_id: $session->external_session_id,
            status: $session->status,
            metadata: $session->metadata,
            started_at: $session->started_at?->toIso8601String(),
            ended_at: $session->ended_at?->toIso8601String(),
            duration_seconds: $durationSeconds,
            transcript_count: $session->transcripts_count ?? $session->transcripts()->count(),
            created_at: $session->created_at?->toIso8601String() ?? '',
            _links: $links,
        );
    }
}
