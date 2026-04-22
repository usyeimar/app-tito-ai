<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent\Conversation;

use App\Models\Tenant\Agent\AgentSession;
use Illuminate\Database\Eloquent\Collection;

final class GetConversationTranscripts
{
    public function __invoke(AgentSession $session): Collection
    {
        return $session->transcripts()->orderBy('timestamp')->get();
    }
}
