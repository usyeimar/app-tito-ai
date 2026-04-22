<?php

declare(strict_types=1);

namespace App\Actions\Tenant\KnowledgeBase;

use App\Data\Tenant\KnowledgeBase\KnowledgeBaseData;
use App\Models\Tenant\KnowledgeBase\KnowledgeBase;

final class ShowKnowledgeBase
{
    public function __invoke(KnowledgeBase $knowledgeBase): KnowledgeBaseData
    {
        return KnowledgeBaseData::from($knowledgeBase);
    }
}
