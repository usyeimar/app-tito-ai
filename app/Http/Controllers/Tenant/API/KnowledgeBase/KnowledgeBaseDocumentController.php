<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\KnowledgeBase;

use App\Data\Tenant\KnowledgeBase\CreateKnowledgeBaseDocumentData;
use App\Data\Tenant\KnowledgeBase\KnowledgeBaseDocumentData;
use App\Data\Tenant\KnowledgeBase\UpdateKnowledgeBaseDocumentData;
use App\Http\Controllers\Controller;
use App\Models\Tenant\KnowledgeBase\KnowledgeBase;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KnowledgeBaseDocumentController extends Controller
{
    public function index(KnowledgeBase $knowledgeBase)
    {
        $documents = KnowledgeBaseDocument::whereHas('category', function ($query) use ($knowledgeBase) {
            $query->where('knowledge_base_id', $knowledgeBase->id);
        })->paginate();

        return KnowledgeBaseDocumentData::collection($documents);
    }

    public function store(CreateKnowledgeBaseDocumentData $data, KnowledgeBase $knowledgeBase)
    {
        $categoryExists = $knowledgeBase->categories()->where('id', $data->knowledge_base_category_id)->exists();
        abort_unless($categoryExists, 422, 'The category does not belong to the selected knowledge base.');

        $authorId = request()->user()->id;
        $slug = Str::slug($data->title) . '-' . Str::random(5);

        $document = DB::transaction(function () use ($data, $authorId, $slug) {
            $doc = KnowledgeBaseDocument::create([
                'knowledge_base_category_id' => $data->knowledge_base_category_id,
                'title' => $data->title,
                'slug' => $slug,
                'content_format' => $data->content_format ?? 'markdown',
                'status' => 'draft',
                'author_id' => $authorId,
            ]);

            $doc->versions()->create([
                'version_number' => 1,
                'content' => $data->content,
                'author_id' => $authorId,
                'change_summary' => 'Initial version',
            ]);

            return $doc;
        });

        return KnowledgeBaseDocumentData::from($document);
    }

    public function show(KnowledgeBase $knowledgeBase, KnowledgeBaseDocument $document)
    {
        return KnowledgeBaseDocumentData::from($document);
    }

    public function update(UpdateKnowledgeBaseDocumentData $data, KnowledgeBase $knowledgeBase, KnowledgeBaseDocument $document)
    {
        $authorId = request()->user()->id;

        DB::transaction(function () use ($data, $document, $authorId) {
            $updateAttributes = [];

            if ($data->title !== null) {
                $updateAttributes['title'] = $data->title;
                $updateAttributes['slug'] = Str::slug($data->title) . '-' . Str::random(5);
            }

            if ($data->status !== null) {
                $updateAttributes['status'] = $data->status;
                if ($data->status === 'published' && $document->status !== 'published') {
                    $updateAttributes['published_at'] = now();
                }
            }

            if (!empty($updateAttributes)) {
                $document->update($updateAttributes);
            }

            // Create a new version if content is provided
            if ($data->content !== null) {
                $latestVersion = $document->versions()->max('version_number') ?? 0;
                
                $document->versions()->create([
                    'version_number' => $latestVersion + 1,
                    'content' => $data->content,
                    'author_id' => $authorId,
                    'change_summary' => 'Content updated',
                ]);
            }
        });

        return KnowledgeBaseDocumentData::from($document->refresh());
    }

    public function destroy(KnowledgeBase $knowledgeBase, KnowledgeBaseDocument $document): JsonResponse
    {
        $document->delete();

        return response()->json(null, 204);
    }
}
