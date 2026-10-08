<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Modules\Identity\Validation\UserAccessRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Create and update share UserAccessRules with the CLI and actions.
 */
class UserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            ? $this->user()->can('update', $target)
            : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $input = UserAccessRules::normalize($this->all());

        if ($this->route('user') instanceof User) {
            $input['is_active'] = $this->boolean('is_active');
        }

        $this->replace($input);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->route('user');

        return UserAccessRules::rules($target instanceof User ? $target : null);
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach (UserAccessRules::consistencyErrors($validator->getData()) as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('ui.name'),
            'email' => __('ui.email'),
            'preferred_locale' => __('ui.users.preferred_locale'),
            'roles' => __('ui.users.roles'),
            'staff_teams' => __('ui.users.staff_teams'),
            'is_active' => __('ui.status'),
            'password' => __('ui.users.temporary_password'),
        ];
    }
}
