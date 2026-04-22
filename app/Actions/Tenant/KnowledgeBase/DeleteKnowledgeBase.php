<?php

declare(strict_types=1);

namespace App\Actions\Tenant\KnowledgeBase;

use App\Models\Tenant\KnowledgeBase\KnowledgeBase;

final class DeleteKnowledgeBase
{
    public function __invoke(KnowledgeBase $knowledgeBase): void
    {
        $knowledgeBase->delete();
    }
}
