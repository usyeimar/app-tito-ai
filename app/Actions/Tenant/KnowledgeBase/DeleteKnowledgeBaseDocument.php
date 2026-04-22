<?php

declare(strict_types=1);

namespace App\Actions\Tenant\KnowledgeBase;

use App\Models\Tenant\KnowledgeBase\KnowledgeBaseDocument;

final class DeleteKnowledgeBaseDocument
{
    public function __invoke(KnowledgeBaseDocument $document): void
    {
        $document->delete();
    }
}
