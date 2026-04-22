<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\API\KnowledgeBase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeBaseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'published', 'archived'])],
        ];
    }
}
