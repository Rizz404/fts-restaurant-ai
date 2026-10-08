<?php

namespace App\Modules\Identity\Validation;

use App\Models\User;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Enums\StaffTeamCode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validation shared by admin forms, CLI commands, and actions so every path
 * that creates or changes an account applies the same rules.
 */
final class UserAccessRules
{
    public const PASSWORD_MIN = 12;

    /**
     * @return array<string, mixed>
     */
    public static function rules(?User $user = null): array
    {
        $creating = $user === null;

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->getKey())],
            'preferred_locale' => ['required', Rule::in(config('restaurant.locales'))],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::in(RoleCode::values())],
            'staff_teams' => ['array'],
            'staff_teams.*' => ['string', 'distinct', Rule::in(StaffTeamCode::values())],
            'is_active' => [$creating ? 'exclude' : 'required', 'boolean'],
            'password' => $creating ? self::passwordRules() : ['exclude'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function passwordRules(): array
    {
        return ['required', 'string', 'min:'.self::PASSWORD_MIN, 'max:255', 'confirmed'];
    }

    /**
     * Normalizes raw input (forms send absent checkboxes as missing keys).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = User::normalizeEmail($input['email']);
        }

        foreach (['roles', 'staff_teams'] as $key) {
            $input[$key] = array_values(array_filter((array) ($input[$key] ?? []), fn ($value) => $value !== null && $value !== ''));
        }

        return $input;
    }

    /**
     * Staff team assignments require the staff role.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function consistencyErrors(array $data): array
    {
        $roles = (array) ($data['roles'] ?? []);
        $errors = [];

        if (($data['staff_teams'] ?? []) !== [] && ! in_array(RoleCode::Staff->value, $roles, true)) {
            $errors['staff_teams'] = __('ui.users.errors.teams_require_staff');
        }

        return $errors;
    }

    /**
     * Full validation for non-HTTP callers (CLI, actions).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $input, ?User $user = null): array
    {
        $validator = Validator::make(self::normalize($input), self::rules($user));
        $validator->after(function ($validator) {
            foreach (self::consistencyErrors($validator->getData()) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        });

        return $validator->validate();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public static function assertConsistent(array $data): void
    {
        $errors = self::consistencyErrors($data);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
