<?php

namespace Tests\Feature;

use App\Models\GuestRequest;
use App\Models\KnowledgeRevision;
use App\Models\RequestEvent;
use App\Models\ReservationSlotUsage;
use App\Models\User;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Knowledge\Actions\CloneRevision;
use App\Modules\Requests\Enums\RequestType;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Real races between two PHP processes on PostgreSQL (row locks, unique
 * constraints). No wrapping test transaction: data is committed so both
 * workers see it, and tables are truncated between tests. Skipped on SQLite,
 * where row locks are no-ops. (Set CONCURRENCY_ALLOW_SQLITE=1 with a
 * *_test.sqlite file only to smoke-test the worker script, never as proof.)
 */
#[Group('postgres')]
class PostgresConcurrencyTest extends TestCase
{
    use BuildsRestaurantData;
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql' && ! (getenv('CONCURRENCY_ALLOW_SQLITE') === '1' && $driver === 'sqlite')) {
            $this->markTestSkipped('Concurrency tests need PostgreSQL (.env.testing).');
        }

        // Real time: the worker processes cannot share this test's frozen clock.
        $this->seedReference();
    }

    /**
     * @param  list<array{0: string, 1: array<string, mixed>}>  $jobs
     * @return list<array<string, mixed>>
     */
    private function race(array $jobs): array
    {
        $startAt = (string) ((int) floor(microtime(true) * 1000) + 2500);
        $processes = array_map(function (array $job) use ($startAt) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-action.php'), $job[0], json_encode($job[1]), $startAt], base_path(), ['APP_ENV' => 'testing']);
            $process->setTimeout(60);
            $process->start();

            return $process;
        }, $jobs);

        return array_map(function (Process $process) {
            $process->wait();
            $decoded = json_decode(trim($process->getOutput()), true);
            $this->assertIsArray($decoded, 'Worker output: '.$process->getOutput().$process->getErrorOutput());

            return $decoded;
        }, $processes);
    }

    public function test_concurrent_confirmations_of_one_draft_create_exactly_one_request_and_one_effect(): void
    {
        $staff = $this->staff('front_of_house');
        [$session, $guest] = $this->makeGuest();
        $draft = $this->draftFor($guest, RequestType::Reservation, $this->reservationPayload(party: 4), ['name' => 'Tamu', 'phone' => '+62 812 3456 7890']);

        $results = $this->race([
            ['confirm', ['session' => $session->id, 'draft' => $draft->id, 'revision' => 1, 'key' => (string) Str::uuid()]],
            ['confirm', ['session' => $session->id, 'draft' => $draft->id, 'revision' => 1, 'key' => (string) Str::uuid()]],
        ]);

        $this->assertTrue($results[0]['ok'] && $results[1]['ok'], json_encode($results));
        $this->assertSame($results[0]['request'], $results[1]['request']);
        $this->assertSame(1, GuestRequest::query()->count());
        $this->assertSame(1, RequestEvent::query()->where('event_code', 'created')->count());
        $this->assertSame(1, $staff->notifications()->count());
        $this->assertSame(4, (int) ReservationSlotUsage::query()->sum('booked_seats'), 'Seats are held once.');
    }

    public function test_concurrent_confirmations_with_the_same_key_replay_one_result(): void
    {
        [$session, $guest] = $this->makeGuest();
        $draft = $this->draftFor($guest, RequestType::Reservation, $this->reservationPayload(party: 2), ['name' => 'Tamu', 'phone' => '+62 812 3456 7890']);
        $key = (string) Str::uuid();

        $results = $this->race([
            ['confirm', ['session' => $session->id, 'draft' => $draft->id, 'revision' => 1, 'key' => $key]],
            ['confirm', ['session' => $session->id, 'draft' => $draft->id, 'revision' => 1, 'key' => $key]],
        ]);

        $this->assertSame(1, GuestRequest::query()->count());
        $this->assertSame([true, false], collect($results)->pluck('created')->sort()->reverse()->values()->all());
    }

    public function test_two_confirmations_for_the_last_seats_let_only_one_win(): void
    {
        [$first, $guestA] = $this->makeGuest();
        [$second, $guestB] = $this->makeGuest();
        [, $slot] = $this->openDay();
        $contact = ['name' => 'Tamu', 'phone' => '+62 812 3456 7890'];
        // 12 + 12 seats do not fit into a slot of 20: exactly one request may succeed.
        $a = $this->draftFor($guestA, RequestType::Reservation, $this->reservationPayload(slot: $slot, party: 10), $contact);
        $b = $this->draftFor($guestB, RequestType::Reservation, $this->reservationPayload(slot: $slot, party: 10), $contact);
        $c = $this->draftFor($guestB, RequestType::Reservation, $this->reservationPayload(slot: $slot, party: 2), $contact);
        $this->confirmDraft($guestA, $a);

        $results = $this->race([
            ['confirm', ['session' => $second->id, 'draft' => $b->id, 'revision' => 1, 'key' => (string) Str::uuid()]],
            ['confirm', ['session' => $second->id, 'draft' => $c->id, 'revision' => 1, 'key' => (string) Str::uuid()]],
        ]);

        // Ten seats are left: the party of 10 and the party of 2 cannot both fit.
        $this->assertSame(1, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame('slot_full', collect($results)->firstWhere('ok', false)['code']);
        $this->assertLessThanOrEqual(20, (int) ReservationSlotUsage::query()->sum('booked_seats'));
        unset($first);
    }

    public function test_concurrent_cancel_and_decline_release_the_seats_once(): void
    {
        $staff = $this->staff('front_of_house');
        $other = $this->staff('front_of_house');
        [, $guest] = $this->makeGuest();
        $request = $this->reservationFor($guest, 6);

        $results = $this->race([
            ['transition', ['actor' => $staff->id, 'request' => $request->id, 'status' => 'declined', 'version' => 1, 'reason' => 'Penuh', 'key' => (string) Str::uuid()]],
            ['transition', ['actor' => $other->id, 'request' => $request->id, 'status' => 'cancelled', 'version' => 1, 'reason' => 'Batal', 'key' => (string) Str::uuid()]],
        ]);

        $this->assertSame(1, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame(0, (int) ReservationSlotUsage::query()->sum('booked_seats'), 'Released once, never negative.');
        $this->assertSame(1, RequestEvent::query()->where('event_code', 'status_changed')->count());
    }

    public function test_two_admins_demoting_each_other_keep_one_admin(): void
    {
        $a = $this->admin();
        $b = $this->admin();

        $results = $this->race([
            ['demote', ['actor' => $a->id, 'target' => $b->id]],
            ['demote', ['actor' => $b->id, 'target' => $a->id]],
        ]);

        $this->assertSame(1, User::query()->active()->withRole(RoleCode::Admin)->count(), json_encode($results));
        $this->assertSame(1, collect($results)->where('ok', true)->count());
    }

    public function test_concurrent_publish_keeps_one_published_revision(): void
    {
        $admin = $this->admin();
        $article = $this->infoArticle('race');
        app(CloneRevision::class)->handle($admin, $article);

        $this->race([
            ['publish', ['actor' => $admin->id, 'article' => $article->id]],
            ['publish', ['actor' => $admin->id, 'article' => $article->id]],
        ]);

        $this->assertSame(1, KnowledgeRevision::query()->where('article_id', $article->id)->where('status', 'published')->count());
        $this->assertSame(1, KnowledgeRevision::query()->where('article_id', $article->id)->where('status', 'archived')->count());
    }

    public function test_concurrent_budget_counting_never_loses_a_turn(): void
    {
        [$session] = $this->makeGuest();
        config(['assistant.guest.daily_turns' => 1]);

        $results = $this->race([
            ['budget', ['session' => $session->id]],
            ['budget', ['session' => $session->id]],
        ]);

        $this->assertSame(2, (int) DB::table('assistant_usage')->whereNotNull('guest_session_id')->value('turns'), json_encode($results));
        $this->assertSame(1, collect($results)->where('over', true)->count(), 'Exactly the second turn is over the limit.');
    }

    public function test_partial_unique_indexes_exist(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('pg_indexes is PostgreSQL only.');
        }

        $info = collect(DB::select("select indexdef from pg_indexes where tablename = 'knowledge_revisions'"))->pluck('indexdef')->implode("\n");
        $usage = collect(DB::select("select indexdef from pg_indexes where tablename = 'assistant_usage'"))->pluck('indexdef')->implode("\n");

        $this->assertStringContainsString('WHERE', $info, 'One published and one draft revision per article are enforced by partial unique indexes.');
        $this->assertStringContainsString('WHERE', $usage, 'One global usage row per day is enforced by a partial unique index.');
    }
}
