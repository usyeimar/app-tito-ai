<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\KnowledgeBase;

use App\Actions\Tenant\KnowledgeBase\CreateKnowledgeBase;
use App\Actions\Tenant\KnowledgeBase\DeleteKnowledgeBase;
use App\Actions\Tenant\KnowledgeBase\ListKnowledgeBases;
use App\Actions\Tenant\KnowledgeBase\ShowKnowledgeBase;
use App\Actions\Tenant\KnowledgeBase\UpdateKnowledgeBase;
use App\Data\Tenant\KnowledgeBase\CreateKnowledgeBaseData;
use App\Data\Tenant\KnowledgeBase\UpdateKnowledgeBaseData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\API\KnowledgeBase\IndexKnowledgeBaseRequest;
use App\Http\Requests\Tenant\API\KnowledgeBase\StoreKnowledgeBaseRequest;
use App\Http\Requests\Tenant\API\KnowledgeBase\UpdateKnowledgeBaseRequest;
use App\Models\Tenant\KnowledgeBase\KnowledgeBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class KnowledgeBaseController extends Controller
{
    public function index(IndexKnowledgeBaseRequest $request, ListKnowledgeBases $action): JsonResponse
    {
        Gate::authorize('viewAny', KnowledgeBase::class);

        $items = $action($request->validated());

        return response()->json(['data' => $items]);
    }

    public function store(StoreKnowledgeBaseRequest $request, CreateKnowledgeBase $action): JsonResponse
    {
        Gate::authorize('create', KnowledgeBase::class);

        $data = CreateKnowledgeBaseData::from($request->validated());
        $result = $action($data);

        return response()->json(['data' => $result, 'message' => 'Knowledge base created.'], 201);
    }

    public function show(KnowledgeBase $knowledgeBase, ShowKnowledgeBase $action): JsonResponse
    {
        Gate::authorize('view', KnowledgeBase::class);

        return response()->json(['data' => $action($knowledgeBase)]);
    }

    public function update(UpdateKnowledgeBaseRequest $request, KnowledgeBase $knowledgeBase, UpdateKnowledgeBase $action): JsonResponse
    {
        Gate::authorize('update', KnowledgeBase::class);

        $data = UpdateKnowledgeBaseData::from($request->validated());
        $result = $action($knowledgeBase, $data);

        return response()->json(['data' => $result, 'message' => 'Knowledge base updated.']);
    }

    public function destroy(KnowledgeBase $knowledgeBase, DeleteKnowledgeBase $action): Response
    {
        Gate::authorize('delete', KnowledgeBase::class);

        $action($knowledgeBase);

        return response()->noContent();
    }
}
