<?php

declare(strict_types=1);

namespace App\Policies\Tenant\KnowledgeBase;

use App\Policies\ModulePolicy;

final class KnowledgeBasePolicy extends ModulePolicy
{
    protected string $module = 'knowledge_base';
}
