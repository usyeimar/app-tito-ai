<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\API\KnowledgeBase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKnowledgeBaseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'knowledge_base_category_id' => ['required', 'string', 'exists:knowledge_base_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'content_format' => ['sometimes', 'string', Rule::in(['markdown', 'html', 'plain'])],
        ];
    }
}
