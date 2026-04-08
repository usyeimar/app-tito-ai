<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\KnowledgeBase;

use App\Data\Tenant\KnowledgeBase\CreateKnowledgeBaseData;
use App\Data\Tenant\KnowledgeBase\KnowledgeBaseData;
use App\Data\Tenant\KnowledgeBase\UpdateKnowledgeBaseData;
use App\Http\Controllers\Controller;
use App\Models\Tenant\KnowledgeBase\KnowledgeBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class KnowledgeBaseController extends Controller
{
    public function index()
    {
        $knowledgeBases = KnowledgeBase::query()->paginate();
        
        return KnowledgeBaseData::collection($knowledgeBases);
    }

    public function store(CreateKnowledgeBaseData $data)
    {
        $attributes = $data->toArray();
        $attributes['slug'] = Str::slug($data->name) . '-' . Str::random(5);

        $knowledgeBase = KnowledgeBase::create($attributes);

        return KnowledgeBaseData::from($knowledgeBase);
    }

    public function show(KnowledgeBase $knowledgeBase)
    {
        return KnowledgeBaseData::from($knowledgeBase);
    }

    public function update(UpdateKnowledgeBaseData $data, KnowledgeBase $knowledgeBase)
    {
        $attributes = array_filter($data->toArray(), fn ($value) => $value !== null);

        if (isset($attributes['name'])) {
            $attributes['slug'] = Str::slug($attributes['name']) . '-' . Str::random(5);
        }

        $knowledgeBase->update($attributes);

        return KnowledgeBaseData::from($knowledgeBase);
    }

    public function destroy(KnowledgeBase $knowledgeBase): JsonResponse
    {
        $knowledgeBase->delete();

        return response()->json(null, 204);
    }
}
