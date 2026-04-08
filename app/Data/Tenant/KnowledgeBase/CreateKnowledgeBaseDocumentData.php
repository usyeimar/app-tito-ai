<?php

namespace App\Data\Tenant\KnowledgeBase;

use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class CreateKnowledgeBaseDocumentData extends Data
{
    public function __construct(
        #[Rule('required|exists:knowledge_base_categories,id')]
        public string $knowledge_base_category_id,
        #[Rule('required|string|max:255')]
        public string $title,
        #[Rule('required|string')]
        public string $content,
        #[Rule('string')]
        public string $content_format,
    ) {}
}
