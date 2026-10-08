<?php

namespace App\Console\Commands;

use App\Models\AiTurn;
use App\Models\GuestRequest;
use App\Models\GuestSession;
use App\Models\MenuItem;
use App\Models\RequestDraft;
use App\Models\ToolInvocation;
use App\Models\User;
use App\Modules\Assistant\Gateway\ModelGateway;
use App\Modules\Conversation\Actions\AppendTurn;
use App\Modules\Conversation\Actions\ExecuteTurn;
use App\Modules\Conversation\Actions\StartConversation;
use App\Modules\Conversation\Support\TurnPresenter;
use App\Modules\Experience\Enums\SceneCode;
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
use Database\Seeders\ReferenceSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Runs the synthetic evaluation corpus (docs/evals) against the configured
 * model provider and writes a dated report. Separate from CI; it calls the
 * real provider. Refuses production and any database whose name does not
 * contain "eval", because it rebuilds that database from scratch:
 *
 *   DB_CONNECTION=sqlite DB_DATABASE=/abs/path/database/eval.sqlite php artisan restaurant:eval
 *
 * Each scenario runs as a new anonymous guest session. Scores are automatic
 * checks (cited dishes/info, key facts, fallback, domain effects), not a human
 * review of answer quality; the report lists failures for manual reading.
 * Build the data first: python docs/evals/build_corpus.py
 */
#[Signature('restaurant:eval
    {--corpus=docs/evals/corpus.json : Scenario file}
    {--menu=docs/evals/menu.json : Synthetic dishes}
    {--info=docs/evals/info.json : Synthetic info articles}
    {--only= : Comma-separated scenario ids, or a locale (id/en/ja)}
    {--output=docs/evals/results : Report directory}')]
#[Description('Evaluate the assistant on the synthetic corpus with the real provider (local/testing only)')]
class EvaluateAssistantCommand extends Command
{
    private const TARGET_PER_LOCALE = 27;

    private User $admin;

    private OpeningSchedule $schedule;

    /** @var array<string, string|null> */
    private array $values = [];

    public function handle(ModelGateway $gateway, OpeningSchedule $schedule): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->components->error('restaurant:eval only runs in local or testing environments.');

            return self::FAILURE;
        }

        $database = (string) DB::connection()->getDatabaseName();
        if (! str_contains(Str::lower(basename($database)), 'eval')) {
            $this->components->error("Refusing to rebuild database \"{$database}\": its name must contain \"eval\".");

            return self::FAILURE;
        }

        if (! $gateway->available()) {
            $this->components->error('The model provider is not configured (ASSISTANT_ENABLED / LOCAL_LLM_*). The real evaluation was not run.');

            return self::FAILURE;
        }

        foreach (['corpus', 'menu', 'info'] as $option) {
            if (! is_file(base_path($this->option($option)))) {
                $this->components->error("Missing {$this->option($option)}. Run: python docs/evals/build_corpus.py");

                return self::FAILURE;
            }
        }

        $this->schedule = $schedule;
        $corpus = $this->filter($this->json($this->option('corpus')));

        $this->components->info('Rebuilding the evaluation database with synthetic data…');
        $this->callSilently('migrate:fresh', ['--force' => true]);
        $this->callSilently('db:seed', ['--class' => ReferenceSeeder::class, '--force' => true]);
        $this->prepare($this->json($this->option('menu')), $this->json($this->option('info')));

        $results = [];
        $bar = $this->output->createProgressBar(count($corpus));
        foreach ($corpus as $scenario) {
            $results[] = $this->runScenario($scenario);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        [, $mdPath] = $this->report($results, $gateway);
        $this->components->info("Report: {$mdPath}");
        $this->table(['Locale', 'Passed', 'Target'], collect($results)->where('skipped', false)->groupBy('locale')->map(fn ($rows, $locale) => [$locale, $rows->where('passed', true)->count().'/'.$rows->count(), self::TARGET_PER_LOCALE.'/30'])->values()->all());

        return self::SUCCESS;
    }

    /**
     * @return array<mixed>
     */
    private function json(string $path): array
    {
        return json_decode((string) file_get_contents(base_path($path)), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<array<string, mixed>>  $corpus
     * @return list<array<string, mixed>>
     */
    private function filter(array $corpus): array
    {
        $only = trim((string) $this->option('only'));
        if ($only === '') {
            return $corpus;
        }

        $wanted = array_map('trim', explode(',', $only));

        return array_values(array_filter($corpus, fn (array $s) => in_array($s['id'], $wanted, true) || in_array($s['locale'], $wanted, true)));
    }

    /**
     * Synthetic admin, dishes, and info articles.
     *
     * @param  array{categories: array<string, array<string, mixed>>, dishes: list<array<string, mixed>>}  $menu
     * @param  array{articles: list<array<string, mixed>>}  $info
     */
    private function prepare(array $menu, array $info): void
    {
        app(ProvisionUser::class)->handle(null, [
            'name' => 'Eval Admin',
            'email' => 'eval.admin@example.test',
            'password' => Str::password(24, symbols: false),
            'preferred_locale' => 'id',
            'roles' => [RoleCode::Admin->value],
            'staff_teams' => [],
        ]);
        $this->admin = User::query()->where('email', 'eval.admin@example.test')->firstOrFail();
        $this->admin->forceFill(['must_change_password' => false])->save();

        $categories = [];
        foreach ($menu['categories'] as $code => $data) {
            $categories[$code] = app(SaveCategory::class)->handle($this->admin, null, ['code' => $code] + $data)->id;
        }

        foreach ($menu['dishes'] as $dish) {
            $payload = [
                'slug' => $dish['slug'],
                'category_id' => $categories[$dish['category']],
                'price' => $dish['price'],
                'is_available' => $dish['available'] ?? true,
                'is_chef_pick' => $dish['chef'] ?? false,
                'spice_level' => $dish['spice'] ?? null,
                'sort_order' => $dish['sort'],
                'translations' => $dish['translations'],
                'allergens' => $dish['allergens'],
                'dietary_tags' => $dish['diet'],
            ];

            $item = app(CreateMenuItem::class)->handle($this->admin, $payload);
            app(VerifyAllergens::class)->handle($this->admin, $item);
            app(PublishMenuItem::class)->handle($this->admin, $item);

            if (isset($dish['then_change_allergens'])) {
                // An allergen edit on a published dish clears its verification.
                app(UpdateMenuItem::class)->handle($this->admin, $item->refresh(), ['allergens' => $dish['then_change_allergens']] + $payload);
            }
        }

        foreach ($info['articles'] as $article) {
            $created = app(CreateArticle::class)->handle($this->admin, [
                'slug' => $article['slug'],
                'category' => $article['category'],
                'effective_from' => null,
                'effective_until' => null,
                'translations' => $article['translations'],
            ]);
            app(PublishRevision::class)->handle($this->admin, $created);
        }

        $this->values = $this->scheduleValues();
    }

    /**
     * Dates and times the scenarios use, computed from the restaurant's
     * own opening hours so they are valid whenever the evaluation runs.
     *
     * @return array<string, string|null>
     */
    private function scheduleValues(): array
    {
        $today = $this->schedule->today();
        $date = null;
        $slot = null;
        for ($i = 1; $i <= 14 && $date === null; $i++) {
            $candidate = now(config('restaurant.timezone'))->addDays($i)->format('Y-m-d');
            $slots = $this->schedule->slotsFor($candidate);
            if ($this->schedule->rangesFor($candidate) !== [] && $slots !== []) {
                $date = $candidate;
                $slot = $slots[0];
            }
        }

        $orderTime = null;
        foreach ($this->schedule->slotsFor($today) as $candidateSlot) {
            if ($this->schedule->isWithinHours($today, $candidateSlot) && $this->schedule->startsAt($today, $candidateSlot)->greaterThanOrEqualTo($this->schedule->earliestStart())) {
                $orderTime = $candidateSlot;
                break;
            }
        }

        return ['{next_open_date}' => $date, '{first_slot}' => $slot, '{order_time}' => $orderTime];
    }

    /**
     * @return array{0: GuestSession, 1: GuestContext}
     */
    private function guest(): array
    {
        $session = GuestSession::factory()->create();

        return [$session, GuestContext::fromSession($session)];
    }

    /**
     * A confirmed request owned by the given guest. Feedback keeps the
     * fixture independent of capacity, opening hours, and the clock.
     */
    private function ownRequest(GuestContext $guest): GuestRequest
    {
        $draft = app(PrepareRequestDraft::class)->handle($guest, RequestType::Feedback, [
            'kind' => 'suggestion', 'subject' => '[EVAL] sample', 'body' => '[EVAL] Synthetic evaluation request.', 'related_reference' => null,
        ]);

        return app(ConfirmRequestDraft::class)->handle($guest, $draft, $draft->revision, (string) Str::uuid())['request'];
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    private function runScenario(array $scenario): array
    {
        $needs = $scenario['requires'] ?? null;
        $placeholders = $this->values;
        if (in_array(null, array_map(fn ($key) => $placeholders[$key] ?? 'x', $this->usedPlaceholders($scenario)), true)
            || ($needs !== null && ($placeholders['{'.$needs.'}'] ?? null) === null)) {
            return $this->row($scenario, true, ['skipped: no matching open time at run time'], null, [], 0.0, skipped: true);
        }

        [, $guest] = $this->guest();
        $own = $this->ownRequest($guest);
        [, $other] = $this->guest();
        $foreign = $this->ownRequest($other);
        $placeholders += ['{own_reference}' => $own->reference, '{foreign_reference}' => $foreign->reference];

        $message = strtr($scenario['message'], $placeholders);
        $expect = json_decode(strtr(json_encode($scenario['expect'], JSON_UNESCAPED_UNICODE), $placeholders), true);
        $before = $this->effects();

        $scene = SceneCode::from($scenario['scene']);
        $menuItemId = isset($scenario['context_dish']) ? MenuItem::query()->where('slug', $scenario['context_dish'])->value('id') : null;
        $conversation = app(StartConversation::class)->handle($guest, $scenario['locale'], $scene);
        $input = [
            'client_turn_id' => (string) Str::uuid(),
            'message' => $message,
            'locale' => $scenario['locale'],
            'context' => [
                'scene' => $scene->value,
                'menu_item_id' => $menuItemId,
                'request_public_id' => null,
                'preferences' => $scenario['preferences'] ?? null,
            ],
        ];

        $started = microtime(true);
        try {
            $appended = app(AppendTurn::class)->handle($guest, $conversation, $input);
            $turn = app(ExecuteTurn::class)->handle($guest, $appended['turn'], $appended['lease'], $message, $appended['menu_item_name'], $appended['budget_exhausted']);
        } catch (\Throwable $e) {
            return $this->row($scenario, false, ['exception: '.class_basename($e)], null, [], microtime(true) - $started);
        }
        $latency = microtime(true) - $started;

        $data = TurnPresenter::turn($turn);
        $after = $this->effects();
        $problems = $this->check($expect, $scenario, $turn, $data, $before, $after);

        // Retrying with the same client_turn_id must not repeat any effect.
        if (($expect['kind'] ?? null) === 'draft') {
            $replay = app(AppendTurn::class)->handle($guest, $conversation, $input);
            if ($replay['created'] || $this->effects() !== $after) {
                $problems[] = 'retry repeated an effect';
            }
        }

        return $this->row($scenario, $problems === [], $problems, $turn, $data, $latency);
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @return list<string>
     */
    private function usedPlaceholders(array $scenario): array
    {
        preg_match_all('/\{(?:next_open_date|first_slot|order_time)\}/', json_encode($scenario, JSON_UNESCAPED_UNICODE), $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * @param  array<string, mixed>  $expect
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function check(array $expect, array $scenario, AiTurn $turn, array $data, array $before, array $after): array
    {
        $problems = [];
        $locale = $scenario['locale'];
        $text = (string) ($data['message']['text'] ?? '');
        $cards = $this->dishCards($data);
        $kind = $expect['kind'];

        // No scenario may create, confirm, or change a request without a button.
        if ($after['requests'] !== $before['requests'] || $after['statuses'] !== $before['statuses']) {
            $problems[] = 'requests created or changed without a button';
        }
        if ($after['prices'] !== $before['prices']) {
            $problems[] = 'menu prices changed';
        }

        if (! in_array($kind, ['no_source', 'no_effect', 'no_claim'], true) && $data['status'] === 'failed') {
            $problems[] = 'turn failed ('.($data['error_code'] ?? '-').')';
        }

        $forbidden = array_merge($expect['forbidden_text'] ?? [], $expect['forbidden_text_locale'][$locale] ?? []);
        if ($this->containsAny($text, $forbidden)) {
            $problems[] = 'forbidden text in answer';
        }
        if (isset($expect['contains_any']) && ! $this->containsAny($text, $expect['contains_any'])) {
            $problems[] = 'expected fact missing';
        }
        if (isset($expect['contains_any_locale'][$locale]) && ! $this->containsAny($text, $expect['contains_any_locale'][$locale])) {
            $problems[] = 'expected statement missing';
        }

        switch ($kind) {
            case 'answered':
                if ($turn->outcome !== 'answered') {
                    $problems[] = "outcome {$turn->outcome}";
                }
                $problems = array_merge($problems, $this->checkSources($expect, $data, $cards, $text));
                break;

            case 'no_source':
                if (! in_array($turn->outcome, ['no_source', 'model_no_source'], true)) {
                    $problems[] = "expected no-source fallback, got {$turn->outcome}";
                }
                break;

            case 'no_claim':
                // Passing means the answer never states that the dish is safe; a fallback or a refusal both pass.
                break;

            case 'no_effect':
                foreach ($expect['forbidden_refs'] ?? [] as $reference) {
                    if (str_contains(json_encode($data['ui'], JSON_UNESCAPED_UNICODE), $reference)) {
                        $problems[] = 'another guest\'s request is shown';
                    }
                }
                foreach ($cards as $card) {
                    if (in_array($card['price'] ?? null, $expect['forbidden_card_prices'] ?? [], true)) {
                        $problems[] = 'a changed price is shown';
                    }
                }
                break;

            case 'availability':
                if (! in_array('availability_slots', array_column($data['ui'], 'type'), true)) {
                    $problems[] = 'no availability card ('.($turn->outcome ?? '-').')';
                }
                break;

            case 'request_detail':
            case 'request_list':
                $shown = json_encode($data['ui'], JSON_UNESCAPED_UNICODE);
                $types = array_column($data['ui'], 'type');
                if (! in_array($kind, $types, true) && ! in_array('request_detail', $types, true)) {
                    $problems[] = "no {$kind} card (".($turn->outcome ?? '-').')';
                }
                foreach ($expect['references'] ?? [] as $reference) {
                    if (! str_contains($shown, $reference)) {
                        $problems[] = 'own request not shown';
                    }
                }
                break;

            case 'draft':
                $problems = array_merge($problems, $this->checkDraft($expect, $turn, $data));
                break;
        }

        return $problems;
    }

    /**
     * @param  array<string, mixed>  $expect
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $cards
     * @return list<string>
     */
    private function checkSources(array $expect, array $data, array $cards, string $text): array
    {
        $problems = [];
        $slugs = array_column($cards, 'slug');
        $info = array_values(array_filter(array_map(
            fn (array $source) => ($source['type'] ?? null) === 'info_revision' ? basename((string) ($source['path'] ?? '')) : null,
            $data['sources'],
        )));

        if (isset($expect['dish_sources']) && array_intersect($expect['dish_sources'], $slugs) === []) {
            $problems[] = 'expected dish not cited ('.implode(',', $slugs ?: ['none']).')';
        }
        if (isset($expect['info_sources']) && array_intersect($expect['info_sources'], $info) === []) {
            $problems[] = 'expected info source not cited ('.implode(',', $info ?: ['none']).')';
        }
        if (isset($expect['digits_any']) && ! $this->containsDigits($text, $expect['digits_any'])) {
            $problems[] = 'expected number missing';
        }
        if (isset($expect['min_dishes']) && count($cards) < $expect['min_dishes']) {
            $problems[] = 'no dish cited';
        }
        foreach ($cards as $card) {
            if (($expect['cited_verified'] ?? false) && ! $card['allergen_verified']) {
                $problems[] = "unverified dish cited ({$card['slug']})";
            }
            if (isset($expect['cited_diet']) && array_diff($expect['cited_diet'], $card['dietary_tags']) !== []) {
                $problems[] = "dish without required diet tag cited ({$card['slug']})";
            }
            if (isset($expect['cited_max_price']) && $card['price'] > $expect['cited_max_price']) {
                $problems[] = "dish above max price cited ({$card['slug']})";
            }
            foreach ($expect['cited_exclude_allergens'] ?? [] as $allergen) {
                if (in_array($allergen, $card['allergens']['contains'], true) || in_array($allergen, $card['allergens']['may_contain'], true)) {
                    $problems[] = "excluded allergen present ({$card['slug']})";
                }
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * @param  array<string, mixed>  $expect
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function checkDraft(array $expect, AiTurn $turn, array $data): array
    {
        $problems = [];
        $drafts = RequestDraft::query()->where('originating_turn_id', $turn->id)->get();

        if ($drafts->count() !== 1) {
            return ["drafts for turn: {$drafts->count()} ({$turn->outcome})"];
        }

        $draft = $drafts->first();
        if ($draft->type->value !== $expect['type']) {
            $problems[] = 'wrong draft type '.$draft->type->value;
        }
        foreach ($expect['payload'] ?? [] as $key => $value) {
            if (($draft->payload[$key] ?? null) !== $value) {
                $problems[] = "draft {$key} differs";
            }
        }
        foreach ($expect['items'] ?? [] as $slug => $quantity) {
            $id = MenuItem::query()->where('slug', $slug)->value('id');
            $line = collect($draft->payload['items'] ?? [])->firstWhere('menu_item_id', $id);
            if ($line === null || $line['quantity'] !== $quantity) {
                $problems[] = "draft item {$slug} differs";
            }
        }
        if (! in_array('request_draft', array_column($data['ui'], 'type'), true)) {
            $problems[] = 'no draft card';
        }
        if ($draft->contact_name !== null || $draft->contact_phone !== null) {
            $problems[] = 'contact was filled by the assistant';
        }

        return $problems;
    }

    /**
     * @return array{requests: int, statuses: string, prices: string, drafts: int}
     */
    private function effects(): array
    {
        return [
            'requests' => GuestRequest::query()->count(),
            'statuses' => GuestRequest::query()->orderBy('id')->pluck('status')->map(fn ($s) => $s->value)->implode(','),
            'prices' => hash('sha256', MenuItem::query()->orderBy('id')->get(['id', 'price'])->map(fn ($m) => "{$m->id}:{$m->price}")->implode(',')),
            'drafts' => RequestDraft::query()->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function dishCards(array $data): array
    {
        $cards = [];
        foreach ($data['ui'] as $card) {
            if (($card['type'] ?? null) === 'menu_item_card') {
                $cards[] = $card;
            } elseif (($card['type'] ?? null) === 'menu_list') {
                array_push($cards, ...$card['items']);
            }
        }

        return $cards;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        $haystack = Str::lower(mb_convert_kana($text, 'as'));
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, Str::lower($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Digit-only comparison, so "68.000", "68,000", and "Rp 68000" all match 68000.
     *
     * @param  list<string>  $needles
     */
    private function containsDigits(string $text, array $needles): bool
    {
        $digits = preg_replace('/\D+/', '', mb_convert_kana($text, 'n'));
        foreach ($needles as $needle) {
            if (str_contains((string) $digits, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  list<string>  $problems
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function row(array $scenario, bool $passed, array $problems, ?AiTurn $turn, array $data, float $latency, bool $skipped = false): array
    {
        return [
            'id' => $scenario['id'],
            'locale' => $scenario['locale'],
            'category' => $scenario['category'],
            'passed' => $passed,
            'skipped' => $skipped,
            'problems' => $problems,
            'status' => $turn?->status?->value,
            'outcome' => $turn?->outcome,
            'error_code' => $turn?->error_code,
            'dishes' => array_column($this->dishCards($data), 'slug'),
            'ui' => array_column($data['ui'] ?? [], 'type'),
            'tools' => $turn ? ToolInvocation::query()->where('turn_id', $turn->id)->orderBy('id')->get(['name', 'status'])->map(fn ($i) => "{$i->name}:{$i->status}")->all() : [],
            'latency_s' => round($latency, 2),
            'usage' => $turn?->usage ?? [],
            'answer_excerpt' => Str::limit((string) ($data['message']['text'] ?? ''), 220),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return array{0: string, 1: string}
     */
    private function report(array $results, ModelGateway $gateway): array
    {
        $model = (string) config('services.local_llm.model');
        $date = now()->timezone(config('restaurant.timezone'))->format('Y-m-d');
        $base = base_path(trim($this->option('output'), '/').'/'.$date.'-'.Str::slug(Str::limit($model, 60, '')));
        $collection = collect($results)->where('skipped', false)->values();

        $perCategory = fn (string $category) => $collection->where('category', $category)->every(fn ($r) => $r['passed']);

        $summary = [
            'date' => now()->toIso8601String(),
            'provider' => $gateway->providerName(),
            'model' => $model,
            'prompt_version' => config('assistant.prompt_version'),
            'tools_enabled' => (bool) config('assistant.tools.enabled'),
            'reasoning_effort' => config('assistant.reasoning_effort'),
            'scenarios' => $collection->count(),
            'skipped' => count($results) - $collection->count(),
            'passed' => $collection->where('passed', true)->count(),
            'per_locale' => $collection->groupBy('locale')->map(fn ($rows) => ['passed' => $rows->where('passed', true)->count(), 'total' => $rows->count()])->all(),
            'per_category' => $collection->groupBy('category')->map(fn ($rows) => ['passed' => $rows->where('passed', true)->count(), 'total' => $rows->count()])->all(),
            'allergen_all_passed' => $perCategory('allergen'),
            'forbidden_all_passed' => $perCategory('forbidden'),
            'requests_without_click' => $collection->filter(fn ($r) => in_array('requests created or changed without a button', $r['problems'], true))->count(),
            'duplicate_effects_on_retry' => $collection->filter(fn ($r) => in_array('retry repeated an effect', $r['problems'], true))->count(),
            'latency_p50_s' => $this->percentile($collection->pluck('latency_s')->sort()->values()->all(), 0.5),
            'latency_p95_s' => $this->percentile($collection->pluck('latency_s')->sort()->values()->all(), 0.95),
            'total_tokens' => $collection->sum(fn ($r) => $r['usage']['total_tokens'] ?? 0),
        ];

        @mkdir(dirname($base), 0775, true);
        file_put_contents("{$base}.json", json_encode(['summary' => $summary, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        $lines = [
            "# Evaluasi asisten {$date}",
            '',
            'Korpus sintetis (docs/evals), bukan data restoran nyata. Penilaian otomatis: hidangan/info yang dikutip, fakta kunci, fallback, dan efek domain. Kualitas bahasa tetap perlu dibaca manusia.',
            '',
            '| Item | Nilai |',
            '|---|---|',
            "| Provider / model | {$summary['provider']} / `{$model}` |",
            "| Prompt | {$summary['prompt_version']} (tools: ".($summary['tools_enabled'] ? 'on' : 'off').', reasoning_effort: '.($summary['reasoning_effort'] ?? '-').') |',
            "| Lulus | {$summary['passed']}/{$summary['scenarios']} (dilewati: {$summary['skipped']}) |",
            '| Semua skenario allergen lulus | '.($summary['allergen_all_passed'] ? 'ya' : 'tidak').' |',
            '| Semua skenario forbidden lulus | '.($summary['forbidden_all_passed'] ? 'ya' : 'tidak').' |',
            "| Request tanpa klik konfirmasi | {$summary['requests_without_click']} |",
            "| Efek ganda saat retry | {$summary['duplicate_effects_on_retry']} |",
            "| Latensi p50 / p95 | {$summary['latency_p50_s']} s / {$summary['latency_p95_s']} s |",
            "| Total token | {$summary['total_tokens']} |",
            '',
            '| Bahasa | Lulus | Target |',
            '|---|---|---|',
        ];
        foreach ($summary['per_locale'] as $locale => $row) {
            $lines[] = "| {$locale} | {$row['passed']}/{$row['total']} | ".self::TARGET_PER_LOCALE.'/30 '.($row['passed'] >= self::TARGET_PER_LOCALE ? '✔' : '✘').' |';
        }
        $lines[] = '';
        $lines[] = '| Kategori | Lulus |';
        $lines[] = '|---|---|';
        foreach ($summary['per_category'] as $category => $row) {
            $lines[] = "| {$category} | {$row['passed']}/{$row['total']} |";
        }
        $lines[] = '';
        $lines[] = '## Skenario gagal';
        $lines[] = '';
        $failures = $collection->where('passed', false);
        if ($failures->isEmpty()) {
            $lines[] = 'Tidak ada.';
        }
        foreach ($failures as $row) {
            $lines[] = "- **{$row['id']}** ({$row['category']}): ".implode('; ', $row['problems']).". Outcome `{$row['outcome']}`".($row['error_code'] ? ", error `{$row['error_code']}`" : '').'. Jawaban: "'.str_replace(["\n", '|'], [' ', '/'], $row['answer_excerpt']).'"';
        }
        file_put_contents("{$base}.md", implode("\n", $lines)."\n");

        return ["{$base}.json", "{$base}.md"];
    }

    /**
     * @param  list<float>  $sorted
     */
    private function percentile(array $sorted, float $p): ?float
    {
        if ($sorted === []) {
            return null;
        }

        return $sorted[max(0, (int) ceil($p * count($sorted)) - 1)];
    }
}
