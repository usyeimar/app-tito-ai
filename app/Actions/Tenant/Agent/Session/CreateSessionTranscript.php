<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent\Session;

use App\Data\Tenant\Agent\Session\CreateAgentSessionTranscriptData;
use App\Models\Tenant\Agent\AgentSession;
use App\Models\Tenant\Agent\AgentSessionTranscript;

final class CreateSessionTranscript
{
    public function __invoke(AgentSession $session, CreateAgentSessionTranscriptData $data): AgentSessionTranscript
    {
        return $session->transcripts()->create([
            'role' => $data->role,
            'content' => $data->content,
            'timestamp' => $data->timestamp ? now()->parse($data->timestamp) : now(),
        ]);
    }
}
