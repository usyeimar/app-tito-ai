<?php

declare(strict_types=1);

namespace App\Actions\Tenant\KnowledgeBase;

use App\Data\Tenant\KnowledgeBase\KnowledgeBaseCategoryData;
use App\Data\Tenant\KnowledgeBase\UpdateKnowledgeBaseCategoryData;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseCategory;

final class UpdateKnowledgeBaseCategory
{
    public function __invoke(KnowledgeBaseCategory $category, UpdateKnowledgeBaseCategoryData $data): KnowledgeBaseCategoryData
    {
        $category->update(array_filter($data->toArray(), fn ($value) => $value !== null));

        return KnowledgeBaseCategoryData::from($category->refresh());
    }
}
