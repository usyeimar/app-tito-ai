<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Web\Me;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, array<int, Rule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }
}
