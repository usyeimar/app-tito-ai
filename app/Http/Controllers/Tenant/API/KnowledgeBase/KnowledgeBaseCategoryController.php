<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\KnowledgeBase;

use App\Data\Tenant\KnowledgeBase\CreateKnowledgeBaseCategoryData;
use App\Data\Tenant\KnowledgeBase\KnowledgeBaseCategoryData;
use App\Data\Tenant\KnowledgeBase\UpdateKnowledgeBaseCategoryData;
use App\Http\Controllers\Controller;
use App\Models\Tenant\KnowledgeBase\KnowledgeBase;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class KnowledgeBaseCategoryController extends Controller
{
    public function index(KnowledgeBase $knowledgeBase)
    {
        $categories = $knowledgeBase->categories()->paginate();

        return KnowledgeBaseCategoryData::collection($categories);
    }

    public function store(CreateKnowledgeBaseCategoryData $data, KnowledgeBase $knowledgeBase)
    {
        $attributes = $data->toArray();
        $attributes['slug'] = Str::slug($data->name) . '-' . Str::random(5);
        
        // Ensure the category is linked to the current Knowledge Base from the route
        $attributes['knowledge_base_id'] = $knowledgeBase->id;

        $category = KnowledgeBaseCategory::create($attributes);

        return KnowledgeBaseCategoryData::from($category);
    }

    public function update(UpdateKnowledgeBaseCategoryData $data, KnowledgeBase $knowledgeBase, KnowledgeBaseCategory $category)
    {
        $attributes = array_filter($data->toArray(), fn ($value) => $value !== null);

        if (isset($attributes['name'])) {
            $attributes['slug'] = Str::slug($attributes['name']) . '-' . Str::random(5);
        }

        $category->update($attributes);

        return KnowledgeBaseCategoryData::from($category);
    }

    public function destroy(KnowledgeBase $knowledgeBase, KnowledgeBaseCategory $category): JsonResponse
    {
        $category->delete();

        return response()->json(null, 204);
    }
}
