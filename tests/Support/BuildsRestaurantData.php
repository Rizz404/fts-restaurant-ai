<?php

namespace Tests\Support;

use App\Models\GuestRequest;
use App\Models\GuestSession;
use App\Models\KnowledgeArticle;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RequestDraft;
use App\Models\User;
use App\Modules\Identity\Actions\ProvisionUser;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Knowledge\Actions\CreateArticle;
use App\Modules\Knowledge\Actions\PublishRevision;
use App\Modules\Menu\Actions\CreateMenuItem;
use App\Modules\Menu\Actions\PublishMenuItem;
use App\Modules\Menu\Actions\SaveCategory;
use App\Modules\Menu\Actions\UpdateMenuItem;
use App\Modules\Menu\Actions\VerifyAllergens;
use App\Modules\Requests\Actions\ConfirmRequestDraft;
use App\Modules\Requests\Actions\PrepareRequestDraft;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Support\OpeningSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Synthetic staff, dishes, info articles, guests, drafts, and requests for
 * feature tests. Uses the real actions so tests exercise the same rules as
 * the application. Time is frozen by freezeClock() for slot and expiry tests.
 */
trait BuildsRestaurantData
{
    protected function seedReference(): void
    {
        $this->seed(ReferenceSeeder::class);
    }

    /**
     * Wednesday 7 October 2026, 09:00 Asia/Jakarta: the restaurant opens at 10:00,
     * so today's slots are after the 60-minute lead time and tomorrow is open.
     */
    protected function freezeClock(string $at = '2026-10-07 09:00:00'): void
    {
        $now = Carbon::parse($at, config('restaurant.timezone'));

        // The application uses both Carbon and CarbonImmutable.
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now->toImmutable());
    }

    // ----- staff ---------------------------------------------------------

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $teams
     * @param  array<string, mixed>  $attributes
     */
    protected function makeUser(array $roles = ['staff'], array $teams = [], array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        ProvisionUser::syncAccess($user, ['roles' => $roles, 'staff_teams' => $teams]);

        return $user->refresh();
    }

    protected function admin(): User
    {
        return $this->makeUser(['admin']);
    }

    protected function editor(): User
    {
        return $this->makeUser(['editor']);
    }

    protected function staff(string $team = 'front_of_house'): User
    {
        return $this->makeUser(['staff'], [$team]);
    }

    // ----- menu and info -------------------------------------------------

    protected function category(string $code = 'mains', ?User $actor = null): MenuCategory
    {
        return MenuCategory::query()->where('code', $code)->first()
            ?? app(SaveCategory::class)->handle($actor ?? $this->adminForData(), null, [
                'code' => $code,
                'sort_order' => 1,
                'is_active' => true,
                'names' => ['id' => ucfirst($code), 'en' => ucfirst($code), 'ja' => ucfirst($code)],
            ]);
    }

    /**
     * Published, allergen-verified dish with id/en/ja text.
     *
     * @param  array<string, string>  $allergens  code => contains|may_contain
     * @param  list<string>  $diet
     * @param  array<string, mixed>  $options  category, available, chef, verified (default true), names (locale => name), aliases (locale => list)
     */
    protected function dish(string $slug, int $price = 40000, array $allergens = [], array $diet = [], array $options = []): MenuItem
    {
        $actor = $this->adminForData();
        $names = $options['names'] ?? ['id' => "Hidangan {$slug}", 'en' => "Dish {$slug}", 'ja' => "料理 {$slug}"];
        $descriptions = $options['descriptions'] ?? ['id' => "Deskripsi {$slug}.", 'en' => "Description of {$slug}.", 'ja' => "{$slug} の説明。"];
        $aliases = $options['aliases'] ?? [];

        $payload = [
            'slug' => $slug,
            'category_id' => $this->category($options['category'] ?? 'mains', $actor)->id,
            'price' => $price,
            'is_available' => $options['available'] ?? true,
            'is_chef_pick' => $options['chef'] ?? false,
            'spice_level' => null,
            'sort_order' => $options['sort'] ?? 1,
            'translations' => collect(['id', 'en', 'ja'])->mapWithKeys(fn (string $locale) => [$locale => [
                'name' => $names[$locale],
                'description' => $descriptions[$locale],
                'aliases' => $aliases[$locale] ?? [],
            ]])->all(),
            'allergens' => $allergens,
            'dietary_tags' => $diet,
        ];

        $item = app(CreateMenuItem::class)->handle($actor, $payload);
        app(VerifyAllergens::class)->handle($actor, $item);
        app(PublishMenuItem::class)->handle($actor, $item);

        if (($options['verified'] ?? true) === false) {
            // Editing allergens on a published dish clears its verification: add a
            // may-contain entry the dish did not have.
            $extra = isset($allergens['lupin']) ? 'mustard' : 'lupin';
            app(UpdateMenuItem::class)->handle($actor, $item->refresh(), ['allergens' => $allergens + [$extra => 'may_contain']] + $payload);
        }

        return $item->refresh();
    }

    protected function unverifiedDish(string $slug, int $price = 40000, array $allergens = ['celery' => 'contains'], array $diet = ['halal'], array $options = []): MenuItem
    {
        return $this->dish($slug, $price, $allergens, $diet, ['verified' => false] + $options);
    }

    /**
     * Published info article with id/en/ja text.
     *
     * @param  array<string, array{title: string, body: string, tags?: list<string>, aliases?: list<string>}>|null  $translations
     */
    protected function infoArticle(string $slug, string $category = 'hours', ?array $translations = null): KnowledgeArticle
    {
        $actor = $this->adminForData();
        $translations ??= [
            'id' => ['title' => "Info {$slug}", 'body' => "Isi informasi {$slug}."],
            'en' => ['title' => "Info {$slug}", 'body' => "Content of {$slug}."],
            'ja' => ['title' => "{$slug} 情報", 'body' => "{$slug} の内容。"],
        ];

        $article = app(CreateArticle::class)->handle($actor, [
            'slug' => $slug,
            'category' => $category,
            'effective_from' => null,
            'effective_until' => null,
            'translations' => array_map(fn (array $t) => $t + ['tags' => [], 'aliases' => []], $translations),
        ]);

        app(PublishRevision::class)->handle($actor, $article);

        return $article->refresh();
    }

    /**
     * The admin that owns synthetic data (created once per test).
     */
    protected function adminForData(): User
    {
        return User::query()->withRole(RoleCode::Admin)->orderBy('id')->first() ?? $this->admin();
    }

    // ----- guests and requests ------------------------------------------

    /**
     * @return array{0: GuestSession, 1: GuestContext}
     */
    protected function makeGuest(string $locale = 'id', ?string $tableLabel = null): array
    {
        $session = GuestSession::factory()->create(['locale' => $locale, 'table_label' => $tableLabel]);

        return [$session, GuestContext::fromSession($session)];
    }

    /**
     * A date at least one day ahead on which the restaurant is open, and its first slot.
     *
     * @return array{0: string, 1: string}
     */
    protected function openDay(int $daysAhead = 1): array
    {
        $schedule = app(OpeningSchedule::class);

        for ($i = $daysAhead; $i < $daysAhead + 14; $i++) {
            $date = Carbon::now(config('restaurant.timezone'))->addDays($i)->format('Y-m-d');
            $slots = $schedule->slotsFor($date);
            if ($slots !== []) {
                return [$date, $slots[0]];
            }
        }

        $this->fail('No open day found in the next two weeks.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $contact
     */
    protected function draftFor(GuestContext $guest, RequestType $type, array $payload, array $contact = []): RequestDraft
    {
        return app(PrepareRequestDraft::class)->handle($guest, $type, $payload, $contact);
    }

    /**
     * @return array<string, mixed>
     */
    protected function reservationPayload(?string $date = null, ?string $slot = null, int $party = 4): array
    {
        [$d, $s] = $this->openDay();

        return ['date' => $date ?? $d, 'slot' => $slot ?? $s, 'party_size' => $party, 'notes' => null, 'seating_preference' => 'any'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function feedbackPayload(string $subject = 'Great evening'): array
    {
        return ['kind' => 'compliment', 'subject' => $subject, 'body' => 'The staff were kind.', 'related_reference' => null];
    }

    /**
     * Confirms a draft for a guest the way the API does.
     */
    protected function confirmDraft(GuestContext $guest, RequestDraft $draft, ?string $key = null): GuestRequest
    {
        return app(ConfirmRequestDraft::class)->handle($guest, $draft, $draft->revision, $key ?? (string) Str::uuid())['request'];
    }

    /**
     * A confirmed (pending) reservation with contact, owned by the guest.
     */
    protected function reservationFor(GuestContext $guest, int $party = 4, ?string $slot = null): GuestRequest
    {
        $draft = $this->draftFor($guest, RequestType::Reservation, $this->reservationPayload(slot: $slot, party: $party), ['name' => 'Tamu Uji', 'phone' => '+62 812 3456 7890', 'email' => null]);

        return $this->confirmDraft($guest, $draft);
    }

    /**
     * A confirmed feedback request (no capacity or clock dependency).
     */
    protected function feedbackFor(GuestContext $guest, string $subject = 'Great evening'): GuestRequest
    {
        return $this->confirmDraft($guest, $this->draftFor($guest, RequestType::Feedback, $this->feedbackPayload($subject)));
    }
}
