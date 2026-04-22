<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent\Conversation;

use App\Models\Tenant\Agent\AgentSession;

final class ShowConversation
{
    public function __invoke(AgentSession $session): AgentSession
    {
        return $session->loadMissing(['agent', 'transcripts', 'audio'])->loadCount('transcripts');
    }
}
