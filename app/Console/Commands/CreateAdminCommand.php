<?php

namespace App\Console\Commands;

use App\Modules\Identity\Actions\ProvisionUser;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Validation\UserAccessRules;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Creates an admin account. The password is read from a hidden prompt and
 * is never accepted as a CLI argument.
 */
#[Signature('restaurant:create-admin {--email= : Admin email address} {--name= : Display name}')]
#[Description('Create an admin account with a temporary password (prompted, never an argument)')]
class CreateAdminCommand extends Command
{
    public function handle(ProvisionUser $provision): int
    {
        $this->callSilently('db:seed', ['--class' => ReferenceSeeder::class, '--force' => true]);

        $email = $this->option('email') ?: $this->ask('Email');
        $name = $this->option('name') ?: $this->ask('Name');
        $password = (string) $this->secret('Temporary password (min '.UserAccessRules::PASSWORD_MIN.' characters)');
        $confirmation = (string) $this->secret('Confirm temporary password');

        try {
            $data = UserAccessRules::validate([
                'name' => $name,
                'email' => $email,
                'preferred_locale' => config('restaurant.default_locale'),
                'roles' => [RoleCode::Admin->value],
                'password' => $password,
                'password_confirmation' => $confirmation,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->components->error($message);
                }
            }

            return self::FAILURE;
        }

        $user = $provision->handle(null, $data);

        $this->components->info("Admin {$user->email} created. The password must be changed at first sign-in.");

        return self::SUCCESS;
    }
}
