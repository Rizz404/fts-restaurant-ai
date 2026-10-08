<?php

namespace Database\Seeders;

use App\Console\Commands\DemoAccountsCommand;
use App\Models\GuestSession;
use App\Models\MenuItem;
use App\Models\User;
use App\Modules\Identity\Actions\ProvisionUser;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Requests\Actions\ConfirmRequestDraft;
use App\Modules\Requests\Actions\PrepareRequestDraft;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Support\OpeningSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Local/testing only: demo staff accounts, demo menu and info, and a few
 * sample requests so every panel has something to show. Never runs in other
 * environments. The demo password is a fixed local value for quick previews;
 * the demo accounts are not for any shared or public environment.
 *
 *   php artisan db:seed --class=LocalDemoSeeder
 */
class LocalDemoSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'demo-restaurant-1';

    public function run(ProvisionUser $provision, PrepareRequestDraft $prepare, ConfirmRequestDraft $confirm, OpeningSchedule $schedule): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('LocalDemoSeeder only runs in local or testing environments.');
        }

        $this->call(ReferenceSeeder::class);

        foreach (DemoAccountsCommand::accounts() as $account) {
            if (User::query()->where('email', $account['email'])->doesntExist()) {
                $provision->handle(null, $account + ['password' => self::DEMO_PASSWORD]);
            }
        }
        User::query()->where('email', 'like', 'demo.%@example.test')->update(['must_change_password' => false]);

        $this->call([MenuDemoSeeder::class, InfoDemoSeeder::class]);

        $this->sampleRequests($prepare, $confirm, $schedule);
    }

    private function sampleRequests(PrepareRequestDraft $prepare, ConfirmRequestDraft $confirm, OpeningSchedule $schedule): void
    {
        $session = GuestSession::factory()->create(['table_label' => 'A3']);
        $guest = GuestContext::fromSession($session);

        $samples = [];

        $date = $this->nextOpenDate($schedule);
        if ($date !== null) {
            $slot = collect($schedule->slotsFor($date))->first();
            if ($slot !== null) {
                $samples[] = [RequestType::Reservation, ['date' => $date, 'slot' => $slot, 'party_size' => 4, 'notes' => '[DEMO] Birthday dinner', 'seating_preference' => 'indoor'], ['name' => 'Demo Guest', 'phone' => '+62 812 0000 0001', 'email' => null]];
            }
        }

        $dish = MenuItem::query()->published()->where('is_available', true)->orderBy('id')->first();
        $time = $schedule->today() !== null ? $this->orderTime($schedule) : null;
        if ($dish !== null && $time !== null) {
            $samples[] = [RequestType::Order, ['fulfilment' => 'pickup', 'requested_time' => $time, 'items' => [['menu_item_id' => $dish->id, 'quantity' => 2]], 'notes' => '[DEMO] Less spicy'], ['name' => 'Demo Guest', 'phone' => '+62 812 0000 0001', 'email' => null]];
        }

        $samples[] = [RequestType::Feedback, ['kind' => 'compliment', 'subject' => '[DEMO] Lovely evening', 'body' => '[DEMO] The staff were friendly and the food was great.', 'related_reference' => null], []];

        foreach ($samples as [$type, $payload, $contact]) {
            try {
                $draft = $prepare->handle($guest, $type, $payload, $contact);
                $confirm->handle($guest, $draft, $draft->revision, (string) Str::uuid());
            } catch (Throwable $e) {
                $this->command?->warn('Skipped sample '.$type->value.': '.$e->getMessage());
            }
        }
    }

    private function nextOpenDate(OpeningSchedule $schedule): ?string
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $schedule->today(), config('restaurant.timezone'));

        for ($i = 1; $i <= 14; $i++) {
            $candidate = $day->addDays($i)->format('Y-m-d');
            if ($schedule->rangesFor($candidate) !== [] && $schedule->slotsFor($candidate) !== []) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * A time today that is inside opening hours and after the lead time, or null.
     */
    private function orderTime(OpeningSchedule $schedule): ?string
    {
        $today = $schedule->today();
        $earliest = $schedule->earliestStart();

        foreach ($schedule->slotsFor($today) as $slot) {
            if ($schedule->startsAt($today, $slot)->greaterThanOrEqualTo($earliest) && $schedule->isWithinHours($today, $slot)) {
                return $slot;
            }
        }

        return null;
    }
}
