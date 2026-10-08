<?php

namespace Tests\Feature;

use App\Models\GuestRequest;
use App\Models\ReservationSlotUsage;
use App\Models\RestaurantSetting;
use App\Modules\Experience\Support\RestaurantSettings;
use App\Modules\Requests\Actions\CancelRequest;
use App\Modules\Requests\Actions\TransitionRequest;
use App\Modules\Requests\Enums\RequestStatus;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Support\OpeningSchedule;
use App\Support\Http\ApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 6: reservation seats per slot (sequential cases; the concurrent
 * race is in PostgresConcurrencyTest). Pending and confirmed reservations
 * hold seats; terminal statuses release them exactly once.
 */
class ReservationCapacityTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->freezeClock();
    }

    private function book(int $party, ?string $slot = null, ?string $date = null): GuestRequest
    {
        [, $guest] = $this->makeGuest();

        return $this->confirmDraft($guest, $this->draftFor($guest, RequestType::Reservation, $this->reservationPayload($date, $slot, $party), ['name' => 'Tamu', 'phone' => '+62 812 3456 7890']));
    }

    private function seats(?string $slot = null): int
    {
        [, $first] = $this->openDay();

        return (int) ReservationSlotUsage::query()->where('slot_start', $slot ?? $first)->sum('booked_seats');
    }

    private function change(GuestRequest $request, RequestStatus $status, ?string $reason = null): void
    {
        app(TransitionRequest::class)->handle($this->staff(), $request->refresh(), $status, $request->version, $reason, (string) Str::uuid());
    }

    public function test_seats_are_held_until_the_slot_is_full(): void
    {
        $capacity = 20;
        $this->book(10);
        $this->book(10);

        $this->assertSame($capacity, $this->seats());
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('slot_full');
        $this->book(1);
    }

    public function test_a_party_that_does_not_fit_the_remaining_seats_is_refused_but_a_smaller_one_fits(): void
    {
        $this->book(10);
        $this->book(8);

        try {
            $this->book(3);
            $this->fail('Three seats do not fit in the two that are left.');
        } catch (ApiException $e) {
            $this->assertSame('slot_full', $e->errorCode);
            $this->assertSame(409, $e->status);
        }

        $this->book(2);
        $this->assertSame(20, $this->seats());
        $this->assertSame(3, GuestRequest::query()->count(), 'A refused confirmation creates nothing.');
    }

    public function test_slots_and_dates_are_independent(): void
    {
        [$date, $first] = $this->openDay();
        $second = app(OpeningSchedule::class)->slotsFor($date)[1];
        [$nextDate] = $this->openDay(2);

        $this->book(10, $first);
        $this->book(10, $first);
        $this->book(10, $second);
        $this->book(10, $first, $nextDate);

        $held = fn (string $day, string $slot) => (int) ReservationSlotUsage::query()->whereDate('date', $day)->where('slot_start', $slot)->sum('booked_seats');
        $this->assertSame(20, $held($date, $first), 'The first slot of the first day is full.');
        $this->assertSame(10, $held($date, $second));
        $this->assertSame(10, (int) ReservationSlotUsage::query()->whereDate('date', $nextDate)->sum('booked_seats'));
    }

    public function test_availability_reflects_held_seats_and_can_book_flags(): void
    {
        [$date, $slot] = $this->openDay();
        $this->book(10);
        $this->book(7);

        $slots = collect($this->getJson("/api/v1/availability?date={$date}&party_size=4")->assertOk()->json('data'))->keyBy('slot');

        $this->assertSame(3, $slots[$slot]['available_seats']);
        $this->assertFalse($slots[$slot]['can_book']);
        $this->assertTrue($slots->except($slot)->every(fn ($row) => $row['available_seats'] === 20 && $row['can_book']));
        $this->assertTrue(collect($this->getJson("/api/v1/availability?date={$date}&party_size=3")->json('data'))->firstWhere('slot', $slot)['can_book']);
    }

    public function test_every_terminal_status_releases_the_seats_once(): void
    {
        foreach ([
            [RequestStatus::Declined, 'Penuh', false],
            [RequestStatus::Cancelled, 'Batal', false],
            [RequestStatus::Completed, null, true],
            [RequestStatus::NoShow, null, true],
        ] as [$terminal, $reason, $needsConfirm]) {
            ReservationSlotUsage::query()->delete();
            $request = $this->book(6);
            $this->assertSame(6, $this->seats());

            if ($needsConfirm) {
                $this->change($request, RequestStatus::Confirmed);
            }
            $this->change($request, $terminal, $reason);
            $this->assertSame(0, $this->seats(), $terminal->value);

            // A terminal request cannot change again, so the seats cannot be released twice.
            try {
                $this->change($request, RequestStatus::Cancelled, 'lagi');
                $this->fail('A closed request accepted another status.');
            } catch (ApiException $e) {
                $this->assertSame('invalid_transition', $e->errorCode);
            }
            $this->assertSame(0, $this->seats());
        }
    }

    public function test_confirming_a_pending_reservation_keeps_its_seats(): void
    {
        $request = $this->book(6);

        $this->change($request, RequestStatus::Confirmed);

        $this->assertSame(6, $this->seats());
    }

    public function test_released_seats_can_be_booked_again(): void
    {
        $first = $this->book(10);
        $this->book(10);

        try {
            $this->book(5);
            $this->fail('The slot was full.');
        } catch (ApiException) {
        }

        $this->change($first, RequestStatus::Declined, 'Maaf');
        $this->book(5);

        $this->assertSame(15, $this->seats());
    }

    public function test_the_guest_cancellation_releases_the_seats_and_never_goes_below_zero(): void
    {
        [, $guest] = $this->makeGuest();
        $request = $this->confirmDraft($guest, $this->draftFor($guest, RequestType::Reservation, $this->reservationPayload(party: 4), ['name' => 'T', 'phone' => '+62 812 3456 7890']));

        app(CancelRequest::class)->handle($guest, $request, null, (string) Str::uuid());
        $this->assertSame(0, $this->seats());

        // Even a damaged counter cannot go negative.
        ReservationSlotUsage::query()->update(['booked_seats' => 1]);
        $other = $this->book(1);
        $this->change($other, RequestStatus::Declined, 'x');
        $this->assertGreaterThanOrEqual(0, $this->seats());
    }

    public function test_orders_and_feedback_never_touch_the_seat_counters(): void
    {
        [, $guest] = $this->makeGuest();
        $this->feedbackFor($guest);
        $dish = $this->dish('rendang', 68000);
        $this->confirmDraft($guest, $this->draftFor($guest, RequestType::Order, [
            'fulfilment' => 'pickup', 'requested_time' => '12:00', 'items' => [['menu_item_id' => $dish->id, 'quantity' => 1]], 'notes' => null,
        ], ['name' => 'T', 'phone' => '+62 812 3456 7890']));

        $this->assertDatabaseCount('reservation_slot_usage', 0);
    }

    public function test_the_admin_can_change_the_capacity_for_new_bookings(): void
    {
        RestaurantSetting::query()->updateOrCreate(['id' => RestaurantSetting::SINGLETON_ID], ['brand_name' => 'X', 'welcome_text' => ['id' => 'a', 'en' => 'a', 'ja' => 'a'], 'slot_capacity' => 5]);
        app(RestaurantSettings::class)->forget();

        $this->book(5);
        $this->expectException(ApiException::class);
        $this->book(1);
    }
}
