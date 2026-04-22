<?php

declare(strict_types=1);

namespace App\Actions\Tenant\KnowledgeBase;

use App\Data\Tenant\KnowledgeBase\KnowledgeBaseDocumentData;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseDocument;

final class ShowKnowledgeBaseDocument
{
    public function __invoke(KnowledgeBaseDocument $document): KnowledgeBaseDocumentData
    {
        return KnowledgeBaseDocumentData::fromModel($document);
    }
}
