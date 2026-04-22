<?php

declare(strict_types=1);

namespace App\Actions\Tenant\KnowledgeBase;

use App\Data\Tenant\KnowledgeBase\KnowledgeBaseDocumentData;
use App\Data\Tenant\KnowledgeBase\UpdateKnowledgeBaseDocumentData;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UpdateKnowledgeBaseDocument
{
    public function __invoke(KnowledgeBaseDocument $document, UpdateKnowledgeBaseDocumentData $data): KnowledgeBaseDocumentData
    {
        $authorId = request()->user()->id;

        DB::transaction(function () use ($data, $document, $authorId): void {
            $updateAttributes = [];

            if ($data->title !== null) {
                $updateAttributes['title'] = $data->title;
                $updateAttributes['slug'] = Str::slug($data->title).'-'.Str::random(5);
            }

            if ($data->status !== null) {
                $updateAttributes['status'] = $data->status;
                if ($data->status === 'published' && $document->status !== 'published') {
                    $updateAttributes['published_at'] = now();
                }
            }

            if (! empty($updateAttributes)) {
                $document->update($updateAttributes);
            }

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

        return KnowledgeBaseDocumentData::fromModel($document->refresh());
    }
}
