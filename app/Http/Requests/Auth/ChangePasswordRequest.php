<?php

namespace App\Http\Requests\Auth;

use App\Modules\Identity\Validation\UserAccessRules;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => [...UserAccessRules::passwordRules(), 'different:current_password'],
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password' => __('ui.auth.current_password'),
            'password' => __('ui.auth.new_password'),
        ];
    }
}
