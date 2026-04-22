<?php

declare(strict_types=1);

namespace App\Actions\Tenant\KnowledgeBase;

use App\Data\Tenant\KnowledgeBase\CreateKnowledgeBaseDocumentData;
use App\Data\Tenant\KnowledgeBase\KnowledgeBaseDocumentData;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateKnowledgeBaseDocument
{
    public function __invoke(CreateKnowledgeBaseDocumentData $data): KnowledgeBaseDocumentData
    {
        $authorId = request()->user()->id;

        $document = DB::transaction(function () use ($data, $authorId): KnowledgeBaseDocument {
            $doc = KnowledgeBaseDocument::create([
                'knowledge_base_category_id' => $data->knowledge_base_category_id,
                'title' => $data->title,
                'slug' => Str::slug($data->title).'-'.Str::random(5),
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

        return KnowledgeBaseDocumentData::fromModel($document);
    }
}
