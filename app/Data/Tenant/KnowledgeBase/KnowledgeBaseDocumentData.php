<?php

namespace App\Data\Tenant\KnowledgeBase;

use Spatie\LaravelData\Data;

class KnowledgeBaseDocumentData extends Data
{
    public function __construct(
        public string $id,
        public string $knowledge_base_category_id,
        public string $title,
        public string $slug,
        public string $content_format,
        public string $status,
        public string $author_id,
        public ?string $published_at,
    ) {}
}
