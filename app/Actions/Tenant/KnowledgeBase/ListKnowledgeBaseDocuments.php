<?php

declare(strict_types=1);

namespace App\Actions\Tenant\KnowledgeBase;

use App\Data\Tenant\KnowledgeBase\KnowledgeBaseDocumentData;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseCategory;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseDocument;
use Illuminate\Support\Collection;

final class ListKnowledgeBaseDocuments
{
    /**
     * @return Collection<int, KnowledgeBaseDocumentData>
     */
    public function __invoke(KnowledgeBaseCategory $category): Collection
    {
        return $category->documents()
            ->get()
            ->map(fn (KnowledgeBaseDocument $doc) => KnowledgeBaseDocumentData::fromModel($doc));
    }
}
