<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\KnowledgeBase;

use App\Actions\Tenant\KnowledgeBase\CreateKnowledgeBaseDocument;
use App\Actions\Tenant\KnowledgeBase\DeleteKnowledgeBaseDocument;
use App\Actions\Tenant\KnowledgeBase\ListKnowledgeBaseDocuments;
use App\Actions\Tenant\KnowledgeBase\ShowKnowledgeBaseDocument;
use App\Actions\Tenant\KnowledgeBase\UpdateKnowledgeBaseDocument;
use App\Data\Tenant\KnowledgeBase\CreateKnowledgeBaseDocumentData;
use App\Data\Tenant\KnowledgeBase\UpdateKnowledgeBaseDocumentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\API\KnowledgeBase\IndexKnowledgeBaseDocumentRequest;
use App\Http\Requests\Tenant\API\KnowledgeBase\StoreKnowledgeBaseDocumentRequest;
use App\Http\Requests\Tenant\API\KnowledgeBase\UpdateKnowledgeBaseDocumentRequest;
use App\Models\Tenant\KnowledgeBase\KnowledgeBase;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseCategory;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class KnowledgeBaseDocumentController extends Controller
{
    public function index(IndexKnowledgeBaseDocumentRequest $request, KnowledgeBaseCategory $category, ListKnowledgeBaseDocuments $action): JsonResponse
    {
        Gate::authorize('viewAny', KnowledgeBase::class);

        $items = $action($category, $request->validated());

        return response()->json(['data' => $items]);
    }

    public function store(StoreKnowledgeBaseDocumentRequest $request, KnowledgeBaseCategory $category, CreateKnowledgeBaseDocument $action): JsonResponse
    {
        Gate::authorize('create', KnowledgeBase::class);

        $data = CreateKnowledgeBaseDocumentData::from($request->validated());
        $result = $action($category, $data);

        return response()->json(['data' => $result, 'message' => 'Knowledge base document created.'], 201);
    }

    public function show(KnowledgeBaseDocument $document, ShowKnowledgeBaseDocument $action): JsonResponse
    {
        Gate::authorize('view', KnowledgeBase::class);

        return response()->json(['data' => $action($document)]);
    }

    public function update(UpdateKnowledgeBaseDocumentRequest $request, KnowledgeBaseDocument $document, UpdateKnowledgeBaseDocument $action): JsonResponse
    {
        Gate::authorize('update', KnowledgeBase::class);

        $data = UpdateKnowledgeBaseDocumentData::from($request->validated());
        $result = $action($document, $data);

        return response()->json(['data' => $result, 'message' => 'Knowledge base document updated.']);
    }

    public function destroy(KnowledgeBaseDocument $document, DeleteKnowledgeBaseDocument $action): Response
    {
        Gate::authorize('delete', KnowledgeBase::class);

        $action($document);

        return response()->noContent();
    }
}
