<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent\Conversation;

use App\Models\Tenant\Agent\AgentSession;

final class DeleteConversation
{
    public function __invoke(AgentSession $session): void
    {
        $session->delete();
    }
}
