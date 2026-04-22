<?php

declare(strict_types=1);

namespace App\Actions\Tenant\KnowledgeBase;

use App\Models\Tenant\KnowledgeBase\KnowledgeBaseCategory;

final class DeleteKnowledgeBaseCategory
{
    public function __invoke(KnowledgeBaseCategory $category): void
    {
        $category->delete();
    }
}
