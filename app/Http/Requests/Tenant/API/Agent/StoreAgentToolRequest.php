<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\API\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentToolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parameters' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
