<?php

declare(strict_types=1);

namespace App\Actions\Tenant\KnowledgeBase;

use App\Data\Tenant\KnowledgeBase\CreateKnowledgeBaseData;
use App\Data\Tenant\KnowledgeBase\KnowledgeBaseData;
use App\Models\Tenant\KnowledgeBase\KnowledgeBase;
use Illuminate\Support\Str;

final class CreateKnowledgeBase
{
    public function __invoke(CreateKnowledgeBaseData $data): KnowledgeBaseData
    {
        $knowledgeBase = KnowledgeBase::create([
            'name' => $data->name,
            'slug' => Str::slug($data->name).'-'.Str::random(5),
            'description' => $data->description,
            'is_public' => $data->is_public,
        ]);

        return KnowledgeBaseData::from($knowledgeBase);
    }
}
