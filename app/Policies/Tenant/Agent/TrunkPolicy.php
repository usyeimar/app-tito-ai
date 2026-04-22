<?php

declare(strict_types=1);

namespace App\Policies\Tenant\Agent;

use App\Policies\ModulePolicy;

final class TrunkPolicy extends ModulePolicy
{
    protected string $module = 'trunk';
}
