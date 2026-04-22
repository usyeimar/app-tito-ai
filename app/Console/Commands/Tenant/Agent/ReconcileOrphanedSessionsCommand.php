<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenant\Agent;

use App\Services\Tenant\Agent\Runner\SessionStateService;
use Illuminate\Console\Command;

class ReconcileOrphanedSessionsCommand extends Command
{
    protected $signature = 'agent:reconcile-sessions {--stale-minutes=60 : Minutes before a session is considered orphaned}';

    protected $description = 'Mark orphaned agent sessions as failed (no session.ended webhook received)';

    public function handle(SessionStateService $sessionState): int
    {
        $staleMinutes = (int) $this->option('stale-minutes');

        $count = $sessionState->reconcileOrphanedSessions($staleMinutes);

        $this->info("Reconciled {$count} orphaned session(s).");

        return self::SUCCESS;
    }
}
