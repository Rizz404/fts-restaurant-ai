<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Identity\Actions\ProvisionUser;
use App\Modules\Identity\Actions\ResetTemporaryPassword;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Enums\StaffTeamCode;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Synthetic staff accounts for local/testing. Refuses any other environment
 * and is never run automatically on deploy. Re-running resets the passwords.
 */
#[Signature('restaurant:demo-accounts')]
#[Description('Create synthetic demo staff accounts with random temporary passwords (local/testing only)')]
class DemoAccountsCommand extends Command
{
    public function handle(ProvisionUser $provision, ResetTemporaryPassword $reset): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->components->error('Demo accounts can only be created in local or testing environments.');

            return self::FAILURE;
        }

        $this->callSilently('db:seed', ['--class' => ReferenceSeeder::class, '--force' => true]);

        $rows = [];

        foreach (self::accounts() as $account) {
            $password = Str::password(16, symbols: false);
            $existing = User::query()->where('email', $account['email'])->first();

            if ($existing === null) {
                $provision->handle(null, $account + ['password' => $password]);
            } else {
                // Trusted system path (null actor) for the reset as well.
                $reset->handle(null, $existing, $password);
            }

            $rows[] = [$account['email'], implode(', ', $account['roles']), implode(', ', $account['staff_teams']), $password];
        }

        $this->table(['Email', 'Roles', 'Teams', 'Temporary password'], $rows);
        $this->components->warn('Passwords are shown once and must be changed at first sign-in. Do not store them in docs.');

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function accounts(): array
    {
        $base = ['preferred_locale' => 'id', 'staff_teams' => []];

        return [
            ['name' => 'Demo Admin', 'email' => 'demo.admin@example.test', 'roles' => [RoleCode::Admin->value]] + $base,
            ['name' => 'Demo Editor', 'email' => 'demo.editor@example.test', 'roles' => [RoleCode::Editor->value]] + $base,
            ['name' => 'Demo Staff Front of House', 'email' => 'demo.foh@example.test', 'roles' => [RoleCode::Staff->value], 'staff_teams' => [StaffTeamCode::FrontOfHouse->value]] + $base,
            ['name' => 'Demo Staff Kitchen', 'email' => 'demo.kitchen@example.test', 'roles' => [RoleCode::Staff->value], 'staff_teams' => [StaffTeamCode::Kitchen->value]] + $base,
        ];
    }
}
