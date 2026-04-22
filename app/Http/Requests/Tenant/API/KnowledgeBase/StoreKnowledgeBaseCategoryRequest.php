<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\API\KnowledgeBase;

use Illuminate\Foundation\Http\FormRequest;

class StoreKnowledgeBaseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'knowledge_base_id' => ['required', 'string', 'exists:knowledge_bases,id'],
            'parent_id' => ['nullable', 'string', 'exists:knowledge_base_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
