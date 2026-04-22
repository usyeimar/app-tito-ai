<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\API\Agent;

use App\Enums\DeploymentChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', Rule::in(array_column(DeploymentChannel::cases(), 'value'))],
            'enabled' => ['sometimes', 'boolean'],
            'config' => ['nullable', 'array'],
            'version' => ['sometimes', 'string', 'max:20'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'pending'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
