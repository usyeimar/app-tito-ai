<?php

declare(strict_types=1);

namespace App\Actions\Tenant\KnowledgeBase;

use App\Data\Tenant\KnowledgeBase\KnowledgeBaseCategoryData;
use App\Models\Tenant\KnowledgeBase\KnowledgeBase;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseCategory;
use Illuminate\Support\Collection;

final class ListKnowledgeBaseCategories
{
    /**
     * @return Collection<int, KnowledgeBaseCategoryData>
     */
    public function __invoke(KnowledgeBase $knowledgeBase): Collection
    {
        return $knowledgeBase->categories()
            ->orderBy('display_order')
            ->get()
            ->map(fn (KnowledgeBaseCategory $cat) => KnowledgeBaseCategoryData::from($cat));
    }
}
