<?php

namespace App\Data\Tenant\KnowledgeBase;

use Spatie\LaravelData\Data;

class KnowledgeBaseData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public bool $is_public,
    ) {}
}
