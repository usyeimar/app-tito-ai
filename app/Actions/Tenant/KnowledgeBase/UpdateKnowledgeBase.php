<?php

declare(strict_types=1);

namespace App\Actions\Tenant\KnowledgeBase;

use App\Data\Tenant\KnowledgeBase\KnowledgeBaseData;
use App\Data\Tenant\KnowledgeBase\UpdateKnowledgeBaseData;
use App\Models\Tenant\KnowledgeBase\KnowledgeBase;

final class UpdateKnowledgeBase
{
    public function __invoke(KnowledgeBase $knowledgeBase, UpdateKnowledgeBaseData $data): KnowledgeBaseData
    {
        $knowledgeBase->update(array_filter($data->toArray(), fn ($value) => $value !== null));

        return KnowledgeBaseData::from($knowledgeBase->refresh());
    }
}
