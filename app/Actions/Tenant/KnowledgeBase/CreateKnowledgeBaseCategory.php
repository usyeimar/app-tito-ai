<?php

declare(strict_types=1);

namespace App\Actions\Tenant\KnowledgeBase;

use App\Data\Tenant\KnowledgeBase\CreateKnowledgeBaseCategoryData;
use App\Data\Tenant\KnowledgeBase\KnowledgeBaseCategoryData;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseCategory;
use Illuminate\Support\Str;

final class CreateKnowledgeBaseCategory
{
    public function __invoke(CreateKnowledgeBaseCategoryData $data): KnowledgeBaseCategoryData
    {
        $category = KnowledgeBaseCategory::create([
            'knowledge_base_id' => $data->knowledge_base_id,
            'parent_id' => $data->parent_id,
            'name' => $data->name,
            'slug' => Str::slug($data->name).'-'.Str::random(5),
            'display_order' => $data->display_order,
        ]);

        return KnowledgeBaseCategoryData::from($category);
    }
}
