<?php

declare(strict_types=1);

namespace App\Actions\Tenant\KnowledgeBase;

use App\Data\Tenant\KnowledgeBase\KnowledgeBaseData;
use App\Models\Tenant\KnowledgeBase\KnowledgeBase;
use Illuminate\Support\Collection;

final class ListKnowledgeBases
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, KnowledgeBaseData>
     */
    public function __invoke(array $filters = []): Collection
    {
        $query = KnowledgeBase::orderBy('name');

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', $search)
                    ->orWhere('description', 'ilike', $search);
            });
        }

        return $query->get()->map(fn (KnowledgeBase $kb) => KnowledgeBaseData::from($kb));
    }
}
