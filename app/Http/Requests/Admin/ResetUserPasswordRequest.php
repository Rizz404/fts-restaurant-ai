<?php

namespace App\Http\Requests\Admin;

use App\Modules\Identity\Validation\UserAccessRules;
use Illuminate\Foundation\Http\FormRequest;

class ResetUserPasswordRequest extends FormRequest
{
    /**
     * Errors go to a separate bag so they render under the reset form only.
     */
    protected $errorBag = 'resetPassword';

    public function authorize(): bool
    {
        return $this->user()->can('resetPassword', $this->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => UserAccessRules::passwordRules(),
        ];
    }

    public function attributes(): array
    {
        return ['password' => __('ui.users.temporary_password')];
    }
}
