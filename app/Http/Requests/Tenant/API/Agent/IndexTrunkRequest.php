<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\API\Agent;

use App\Http\Requests\Shared\Concerns\HasCanonicalSearchRules;
use Illuminate\Foundation\Http\FormRequest;

class IndexTrunkRequest extends FormRequest
{
    use HasCanonicalSearchRules;

    public function rules(): array
    {
        return $this->canonicalSearchRules();
    }
}
