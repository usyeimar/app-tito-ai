<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\KnowledgeBase;

use App\Actions\Tenant\KnowledgeBase\CreateKnowledgeBaseCategory;
use App\Actions\Tenant\KnowledgeBase\DeleteKnowledgeBaseCategory;
use App\Actions\Tenant\KnowledgeBase\ListKnowledgeBaseCategories;
use App\Actions\Tenant\KnowledgeBase\ShowKnowledgeBaseCategory;
use App\Actions\Tenant\KnowledgeBase\UpdateKnowledgeBaseCategory;
use App\Data\Tenant\KnowledgeBase\CreateKnowledgeBaseCategoryData;
use App\Data\Tenant\KnowledgeBase\UpdateKnowledgeBaseCategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\API\KnowledgeBase\IndexKnowledgeBaseCategoryRequest;
use App\Http\Requests\Tenant\API\KnowledgeBase\StoreKnowledgeBaseCategoryRequest;
use App\Http\Requests\Tenant\API\KnowledgeBase\UpdateKnowledgeBaseCategoryRequest;
use App\Models\Tenant\KnowledgeBase\KnowledgeBase;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class KnowledgeBaseCategoryController extends Controller
{
    public function index(IndexKnowledgeBaseCategoryRequest $request, KnowledgeBase $knowledgeBase, ListKnowledgeBaseCategories $action): JsonResponse
    {
        Gate::authorize('viewAny', KnowledgeBase::class);

        $items = $action($knowledgeBase, $request->validated());

        return response()->json(['data' => $items]);
    }

    public function store(StoreKnowledgeBaseCategoryRequest $request, KnowledgeBase $knowledgeBase, CreateKnowledgeBaseCategory $action): JsonResponse
    {
        Gate::authorize('create', KnowledgeBase::class);

        $data = CreateKnowledgeBaseCategoryData::from($request->validated());
        $result = $action($knowledgeBase, $data);

        return response()->json(['data' => $result, 'message' => 'Knowledge base category created.'], 201);
    }

    public function show(KnowledgeBaseCategory $category, ShowKnowledgeBaseCategory $action): JsonResponse
    {
        Gate::authorize('view', KnowledgeBase::class);

        return response()->json(['data' => $action($category)]);
    }

    public function update(UpdateKnowledgeBaseCategoryRequest $request, KnowledgeBaseCategory $category, UpdateKnowledgeBaseCategory $action): JsonResponse
    {
        Gate::authorize('update', KnowledgeBase::class);

        $data = UpdateKnowledgeBaseCategoryData::from($request->validated());
        $result = $action($category, $data);

        return response()->json(['data' => $result, 'message' => 'Knowledge base category updated.']);
    }

    public function destroy(KnowledgeBaseCategory $category, DeleteKnowledgeBaseCategory $action): Response
    {
        Gate::authorize('delete', KnowledgeBase::class);

        $action($category);

        return response()->noContent();
    }
}
