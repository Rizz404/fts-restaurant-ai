<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Modules\Identity\Enums\RoleCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password-for-tests'),
            'preferred_locale' => 'id',
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withTemporaryPassword(): static
    {
        return $this->state(fn () => ['must_change_password' => true]);
    }

    public function withRoles(RoleCode ...$roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles) {
            $ids = array_map(fn (RoleCode $role) => Role::firstOrCreate(['code' => $role->value])->id, $roles);
            $user->roles()->syncWithoutDetaching($ids);
        });
    }
}
