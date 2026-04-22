<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\API\KnowledgeBase;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKnowledgeBaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }
}
