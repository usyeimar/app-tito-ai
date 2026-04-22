<?php

declare(strict_types=1);

namespace App\Actions\Tenant\Agent\Conversation;

use App\Models\Tenant\Agent\AgentSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListConversations
{
    /** @param array<string, mixed> $filters */
    public function __invoke(array $filters = []): LengthAwarePaginator
    {
        $query = AgentSession::with('agent')
            ->withCount('transcripts')
            ->orderByDesc('started_at');

        if (! empty($filters['filter']['agent_id'])) {
            $query->where('agent_id', $filters['filter']['agent_id']);
        }

        if (! empty($filters['filter']['status'])) {
            $query->where('status', $filters['filter']['status']);
        }

        if (! empty($filters['filter']['channel'])) {
            $query->where('channel', $filters['filter']['channel']);
        }

        if (! empty($filters['filter']['started_after'])) {
            $query->where('started_at', '>=', $filters['filter']['started_after']);
        }

        if (! empty($filters['filter']['started_before'])) {
            $query->where('started_at', '<=', $filters['filter']['started_before']);
        }

        $perPage = $filters['page']['size'] ?? 20;
        $page = $filters['page']['number'] ?? 1;

        return $query->paginate(perPage: (int) $perPage, page: (int) $page);
    }
}
