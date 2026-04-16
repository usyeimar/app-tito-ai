<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Web\Me;

use App\Models\Central\Auth\Authentication\CentralUser;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule as ValidationRule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, array<int, Rule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                ValidationRule::unique(CentralUser::class)->ignore($this->user()?->id),
            ],
        ];
    }
}
