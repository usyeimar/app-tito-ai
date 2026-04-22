<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\API\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'language' => ['sometimes', 'string', 'max:10'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'number_format' => ['sometimes', 'string', 'max:20'],
            'knowledge_base_id' => ['nullable', 'string', 'ulid'],
            'brain_config' => ['nullable', 'array'],
            'runtime_config' => ['nullable', 'array'],
            'architecture_config' => ['nullable', 'array'],
            'capabilities_config' => ['nullable', 'array'],
            'observability_config' => ['nullable', 'array'],
            'from_scratch' => ['sometimes', 'boolean'],
        ];
    }
}
