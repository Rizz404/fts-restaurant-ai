<?php

namespace App\Modules\Conversation\Actions;

use App\Models\AiTurn;
use App\Models\GuestRequest;
use App\Models\GuestSession;
use App\Modules\Assistant\Evidence\EvidencePool;
use App\Modules\Assistant\Gateway\ChatCompletionRequest;
use App\Modules\Assistant\Gateway\ChatCompletionResult;
use App\Modules\Assistant\Gateway\GatewayException;
use App\Modules\Assistant\Gateway\ModelGateway;
use App\Modules\Assistant\Gateway\TurnDeadline;
use App\Modules\Assistant\Prompt\AllergenClaimGuard;
use App\Modules\Assistant\Prompt\AnswerValidator;
use App\Modules\Assistant\Prompt\PromptComposer;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolExecutor;
use App\Modules\Assistant\Tools\ToolRegistry;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Conversation\Support\QuickReplies;
use App\Modules\Conversation\Support\TurnCards;
use App\Modules\Conversation\Support\TurnLease;
use App\Modules\Conversation\Support\TurnRecorder;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Knowledge\KnowledgeRetriever;
use App\Modules\Knowledge\Queries\VisibleRevisions;
use App\Modules\Menu\Data\MenuFilters;
use App\Modules\Menu\MenuRetriever;
use App\Modules\Requests\Presenters\RequestCards;
use App\Modules\Requests\Support\RequestAccess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Runs one AI turn outside any database transaction:
 *
 *  1. Budget exhausted → fixed fallback, the model is not called.
 *  2. Deterministic replies for greetings/thanks/navigation (no model).
 *  3. Retrieval before the completion: dishes (max 8, the dish on screen
 *     first) and info articles (max 5). Without tools, no evidence → no-source
 *     fallback without calling the model.
 *  4. Without tools: one completion validated by AnswerValidator.
 *  5. With tools (Plan 7): a loop of at most 6 rounds / 7 completions / 5 calls
 *     per response / 12 invocations within the 60 s deadline. Availability,
 *     request, and draft results are terminal and their reply is written by
 *     the server from canonical data; menu/info answers still go through
 *     AnswerValidator with sources from this turn only.
 *  6. Provider disabled → ai_unavailable fallback with related dishes/articles.
 *  7. Before saving: the guest session still exists, the lease is still owned,
 *     and every cited dish/article is still published. Cards are rebuilt by
 *     the server from the database.
 *
 * Every failure stores a failed turn with a fallback message; the guest's
 * message is never deleted. A draft saved before a failure is still shown
 * for review and never prepared twice. The lease is always released.
 */
class ExecuteTurn
{
    /** @var array{completion_calls: int, tool_rounds: int, tool_invocations: int} */
    private array $stats = ['completion_calls' => 0, 'tool_rounds' => 0, 'tool_invocations' => 0];

    public function __construct(
        private readonly ModelGateway $gateway,
        private readonly MenuRetriever $menu,
        private readonly KnowledgeRetriever $knowledge,
        private readonly PromptComposer $composer,
        private readonly AnswerValidator $validator,
        private readonly ToolRegistry $tools,
        private readonly ToolExecutor $executor,
        private readonly ReadHistory $history,
    ) {}

    public function handle(GuestContext $guest, AiTurn $turn, TurnLease $lease, string $message, ?string $menuItemName = null, bool $budgetExhausted = false): AiTurn
    {
        $started = microtime(true);
        $outcome = 'unknown';
        $this->stats = ['completion_calls' => 0, 'tool_rounds' => 0, 'tool_invocations' => 0];

        try {
            $outcome = $this->run($guest, $turn, $lease, $message, $menuItemName, $budgetExhausted);
        } catch (\Throwable $e) {
            report($e);
            $outcome = 'server_error';
            $this->fail($turn, 'server_error', __('deck.assistant.error', [], $turn->locale), $this->draftCards($turn));
        } finally {
            $lease->release();
        }

        // Outcome for operations metrics (e.g. retrieval no-match); never message text.
        AiTurn::query()->whereKey($turn->id)->update(['outcome' => mb_substr($outcome, 0, 64)]);

        Log::info('assistant.turn', [
            'turn_id' => $turn->id,
            'guest_session' => $guest->publicId,
            'prompt_version' => $turn->prompt_version,
            'provider' => $turn->provider,
            'model' => $turn->model,
            'result' => $outcome,
            'status' => $turn->fresh()?->status?->value,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'usage' => $turn->usage,
        ] + $this->stats);

        return $turn->refresh();
    }

    private function run(GuestContext $guest, AiTurn $turn, TurnLease $lease, string $message, ?string $menuItemName, bool $budgetExhausted): string
    {
        $locale = $turn->locale;
        $context = $turn->context_snapshot;
        $scene = SceneCode::tryFrom($context['scene'] ?? '') ?? SceneCode::Welcome;
        $toolsEnabled = ! $this->tools->isEmpty();

        app()->setLocale($locale);

        if ($budgetExhausted) {
            $this->fail($turn, 'ai_budget_exhausted', __('deck.assistant.budget_exhausted'), TurnCards::forPool(new EvidencePool, TurnCards::helpActions()));

            return 'budget_exhausted';
        }

        if ($quick = QuickReplies::match($message)) {
            $this->complete($turn, $lease, $guest, $quick['text'], new EvidencePool, $quick['actions']);

            return 'quick_'.$quick['kind'];
        }

        $pool = $this->retrieve($message, $locale, $context['menu_item_id'] ?? null);

        if ($pool->isEmpty() && ! $toolsEnabled) {
            $this->complete($turn, $lease, $guest, $this->noSourceText($scene), new EvidencePool, TurnCards::helpActions());

            return 'no_source';
        }

        if (! $this->gateway->available()) {
            $this->fail($turn, GatewayException::UNAVAILABLE, __('deck.assistant.unavailable'), TurnCards::forPool($pool, TurnCards::helpActions()), [], $pool);

            return GatewayException::UNAVAILABLE;
        }

        $preferences = $context['preferences'] ?? null;
        $messages = $this->composer->compose(
            $locale,
            [
                'scene' => $scene->value,
                'menu_item' => ($context['menu_item_id'] ?? null) !== null && $menuItemName !== null ? ['id' => (int) $context['menu_item_id'], 'name' => $menuItemName] : null,
                'preferences' => $preferences,
                'request' => $this->requestContext($guest, $context['request_public_id'] ?? null),
            ],
            $pool,
            $this->history->forPrompt($turn->conversation, $turn->id),
            $message,
            $toolsEnabled,
        );

        $deadline = new TurnDeadline($turn->deadline_at);

        return $toolsEnabled
            ? $this->converse($guest, $turn, $lease, $message, $messages, $pool, $deadline, $preferences)
            : $this->answerOnce($guest, $turn, $lease, $message, $messages, $pool, $deadline);
    }

    /**
     * The request open on screen, only when this guest owns it or opened it
     * with reference + contact (the prompt gets its reference, nothing else).
     *
     * @return array{public_id: string, reference: string}|null
     */
    private function requestContext(GuestContext $guest, ?string $publicId): ?array
    {
        if ($publicId === null || ! RequestAccess::guestCanView($guest, $publicId)) {
            return null;
        }

        $request = GuestRequest::query()->where('public_id', strtolower($publicId))->first(['public_id', 'reference']);

        return $request === null ? null : ['public_id' => $request->public_id, 'reference' => $request->reference];
    }

    /**
     * One completion, no tools.
     *
     * @param  list<array<string, mixed>>  $messages
     */
    private function answerOnce(GuestContext $guest, AiTurn $turn, TurnLease $lease, string $message, array $messages, EvidencePool $pool, TurnDeadline $deadline): string
    {
        try {
            $this->stats['completion_calls']++;
            $result = $this->gateway->complete(new ChatCompletionRequest($messages), $deadline);
        } catch (GatewayException $e) {
            $this->fail($turn, $e->errorCode, __('deck.assistant.error'), TurnCards::forPool($pool, TurnCards::helpActions()), ['provider' => $this->gateway->providerName()], $pool);

            return $e->errorCode;
        }

        $meta = ['provider' => $this->gateway->providerName(), 'model' => $result->model, 'usage' => $result->usage];

        return $this->finalAnswer($guest, $turn, $lease, $message, $result, $pool, $pool, $meta, null);
    }

    /**
     * Tool loop.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>|null  $preferences
     */
    private function converse(GuestContext $guest, AiTurn $turn, TurnLease $lease, string $message, array $messages, EvidencePool $initial, TurnDeadline $deadline, ?array $preferences): string
    {
        $pool = new EvidencePool;
        $pool->merge($initial);
        $meta = ['provider' => $this->gateway->providerName(), 'model' => null, 'usage' => []];
        $context = new ToolContext($turn, $turn->conversation, $turn->locale, $preferences);
        $definitions = $this->tools->definitions();
        /** @var ToolResult|null $draft */
        $draft = null;
        /** @var list<array<string, mixed>> $offered Buttons from non-terminal tools (present_slide). */
        $offered = [];

        $maxCompletions = (int) config('assistant.max_completion_calls');
        $maxRounds = (int) config('assistant.max_tool_rounds');
        $perResponse = (int) config('assistant.max_tool_calls_per_response');
        $maxInvocations = (int) config('assistant.max_total_invocations');

        while ($this->stats['completion_calls'] < $maxCompletions) {
            if ($deadline->expired()) {
                return $this->toolFailure($turn, GatewayException::TIMEOUT, $initial, $draft, $meta);
            }

            try {
                $this->stats['completion_calls']++;
                $result = $this->gateway->complete(new ChatCompletionRequest($messages, $definitions), $deadline);
            } catch (GatewayException $e) {
                // A 400 on the first request that carries tools usually means
                // the provider/model does not support function calling.
                $code = $e->errorCode === GatewayException::BAD_REQUEST && $this->stats['completion_calls'] === 1
                    ? 'tool_capability_unavailable'
                    : $e->errorCode;

                return $this->toolFailure($turn, $code, $initial, $draft, $meta);
            }

            $meta['model'] = $result->model ?? $meta['model'];
            $meta['usage'] = $this->addUsage($meta['usage'], $result->usage);

            if ($result->toolCalls === []) {
                return $this->finalAnswer($guest, $turn, $lease, $message, $result, $pool, $initial, $meta, $draft, $offered);
            }

            if ($this->stats['tool_rounds'] >= $maxRounds) {
                return $this->toolFailure($turn, 'tool_limit_exceeded', $initial, $draft, $meta);
            }
            $this->stats['tool_rounds']++;

            $calls = $this->normalizeCalls($result->toolCalls);
            $allowed = array_slice($calls, 0, max(0, min($perResponse, $maxInvocations - $this->stats['tool_invocations'])));

            $messages[] = [
                'role' => 'assistant',
                'content' => $this->visibleText($result->text),
                'tool_calls' => array_map(fn (array $call) => [
                    'id' => $call['id'],
                    'type' => 'function',
                    'function' => ['name' => $call['name'], 'arguments' => $call['arguments']],
                ], $allowed),
            ];

            $terminal = [];
            foreach ($allowed as $call) {
                $this->stats['tool_invocations']++;
                $toolResult = $this->executor->run($guest, $call, $context);

                if ($toolResult->evidence !== null) {
                    $pool->merge($toolResult->evidence);
                }
                if ($toolResult->operation !== null) {
                    $draft = $toolResult;
                }
                if ($toolResult->terminal) {
                    $terminal[] = $toolResult;
                } else {
                    array_push($offered, ...$toolResult->actions);
                }

                $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'], 'content' => $toolResult->forModel()];
            }

            if (count($allowed) < count($calls)) {
                // Calls above the per-response or per-turn limit are not run.
                return $this->toolFailure($turn, 'tool_limit_exceeded', $initial, $draft, $meta);
            }

            if ($terminal !== []) {
                $reply = $this->terminalReply($terminal, $draft);
                $this->completeWithCards($turn, $lease, $guest, $reply['text'], $reply['cards'], null, $meta);

                return 'tool_'.implode('_', array_unique(array_map(fn (ToolResult $item) => $item->code, $terminal)));
            }
        }

        return $this->toolFailure($turn, 'tool_limit_exceeded', $initial, $draft, $meta);
    }

    /**
     * Validates a final model answer against the evidence of this turn.
     *
     * @param  array<string, mixed>  $meta
     */
    private function finalAnswer(GuestContext $guest, AiTurn $turn, TurnLease $lease, string $message, ChatCompletionResult $result, EvidencePool $pool, EvidencePool $initial, array $meta, ?ToolResult $draft, array $offered = []): string
    {
        $answer = $this->validator->validate($result, $pool->menuIds(), $pool->revisionIds(), ! $this->tools->isEmpty());

        if (! $answer['ok']) {
            if ($draft !== null) {
                return $this->toolFailure($turn, $answer['code'], $initial, $draft, $meta);
            }

            if ($answer['code'] === AnswerValidator::NO_SOURCE) {
                $this->complete($turn, $lease, $guest, $this->noSourceText(SceneCode::tryFrom($turn->context_snapshot['scene'] ?? '') ?? SceneCode::Welcome), new EvidencePool, TurnCards::helpActions(), $meta);

                return 'model_no_source';
            }

            $this->fail($turn, $answer['code'], __('deck.assistant.error'), TurnCards::forPool($initial, TurnCards::helpActions()), $meta, $initial);

            return $answer['code'];
        }

        $used = $pool
            ->only($answer['source_menu_item_ids'], $answer['source_revision_ids'])
            ->limited((int) config('assistant.evidence.max_menu_items'), (int) config('assistant.evidence.max_info_items'));

        $text = $answer['text'];
        $outcome = 'answered';
        if (AllergenClaimGuard::mustReplace($message, $text)) {
            // Never relay a safety or absence claim about allergens.
            $text = __('deck.assistant.allergen_safe_claim');
            $outcome = 'allergen_guard';
        }

        $this->complete($turn, $lease, $guest, $text, $used, $offered, $meta);

        return $outcome;
    }

    /**
     * Server-written reply for terminal tool results (availability, requests, drafts).
     *
     * @param  list<ToolResult>  $results
     * @return array{text: string, cards: array<string, mixed>}
     */
    private function terminalReply(array $results, ?ToolResult $draft): array
    {
        $texts = [];
        $ui = [];
        $actions = [];
        foreach ($results as $result) {
            $texts[] = (string) $result->text;
            array_push($ui, ...$result->ui);
            foreach ($result->actions as $action) {
                $actions[($action['type'] ?? '').'|'.($action['path'] ?? '')] = $action;
            }
        }

        return [
            'text' => implode("\n\n", array_values(array_unique(array_filter($texts)))),
            'cards' => ['sources' => [], 'ui' => $ui, 'actions' => array_values($actions), 'operation' => $draft?->operation],
        ];
    }

    /**
     * Failed turn after the tool loop started. A draft saved earlier in the
     * turn is still returned for review; prepare_* is never re-run.
     *
     * @param  array<string, mixed>  $meta
     */
    private function toolFailure(AiTurn $turn, string $code, EvidencePool $initial, ?ToolResult $draft, array $meta): string
    {
        if ($draft !== null) {
            $this->fail($turn, $code, __('deck.assistant.tools.draft_fallback'), [
                'sources' => [],
                'ui' => $draft->ui,
                'actions' => $draft->actions,
                'operation' => $draft->operation,
            ], $meta);

            return $code;
        }

        $text = $code === 'tool_capability_unavailable' ? __('deck.assistant.tools.tool_capability_unavailable') : __('deck.assistant.error');
        $this->fail($turn, $code, $text, TurnCards::forPool($initial, TurnCards::helpActions()), $meta, $initial);

        return $code;
    }

    /**
     * Cards for a draft already saved by this turn (used after a crash).
     *
     * @return array<string, mixed>|null
     */
    private function draftCards(AiTurn $turn): ?array
    {
        return RequestCards::draftCardsForTurn($turn);
    }

    /**
     * @param  list<array{id: string, name: string, arguments: string}>  $calls
     * @return list<array{id: string, name: string, arguments: string}>
     */
    private function normalizeCalls(array $calls): array
    {
        return array_map(fn (array $call) => [
            'id' => $call['id'] !== '' ? mb_substr($call['id'], 0, 128) : 'call_'.Str::lower(Str::random(16)),
            'name' => $call['name'],
            'arguments' => $call['arguments'],
        ], $calls);
    }

    private function visibleText(?string $text): ?string
    {
        $text = trim((string) preg_replace('/^\s*<think>.*?<\/think>/s', '', (string) $text));

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, int>  $total
     * @param  array<string, int>  $usage
     * @return array<string, int>
     */
    private function addUsage(array $total, array $usage): array
    {
        foreach ($usage as $key => $value) {
            $total[$key] = ($total[$key] ?? 0) + $value;
        }

        return $total;
    }

    private function noSourceText(SceneCode $scene): string
    {
        return match ($scene) {
            SceneCode::Menu, SceneCode::Picks => __('deck.assistant.no_source_menu'),
            SceneCode::Info => __('deck.assistant.no_source_info'),
            default => __('deck.assistant.no_source'),
        };
    }

    /**
     * Initial retrieval: the dish on screen first, then dishes and info
     * articles matching the message (bounded).
     */
    private function retrieve(string $message, string $locale, ?int $contextItemId): EvidencePool
    {
        $pool = new EvidencePool;

        if ($contextItemId !== null && ($item = $this->menu->findEvidence($contextItemId, $locale)) !== null) {
            $pool->addMenu($item);
        }

        $dishes = $this->menu->search($locale, MenuFilters::fromArray(['query' => $message]));
        foreach ($dishes->items as $item) {
            $pool->addMenu($item);
        }

        foreach ($this->knowledge->search($locale, $message) as $item) {
            $pool->addInfo($item);
        }

        return $pool->limited((int) config('assistant.evidence.max_menu_items'), (int) config('assistant.evidence.max_info_items'));
    }

    /**
     * Rebuilds the cited evidence from the database; null when any cited
     * dish or article is no longer published (a stale answer must not be saved).
     */
    private function verifiedPool(EvidencePool $pool, string $locale): ?EvidencePool
    {
        $fresh = new EvidencePool;

        foreach ($pool->menu() as $item) {
            $current = $this->menu->findEvidence($item->itemId, $locale);
            if ($current === null) {
                return null;
            }
            $fresh->addMenu($current);
        }

        if ($pool->info() !== []) {
            $visible = VisibleRevisions::query()
                ->whereIn('knowledge_revisions.id', $pool->revisionIds())
                ->pluck('knowledge_revisions.id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count($visible) !== count($pool->info())) {
                return null;
            }
            foreach ($pool->info() as $item) {
                $fresh->addInfo($item);
            }
        }

        return $fresh;
    }

    /**
     * Commits a completed turn built from an evidence pool, after
     * re-verifying the guest session, the lease, and the sources.
     *
     * @param  list<array<string, mixed>>  $actions
     * @param  array<string, mixed>  $meta
     */
    private function complete(AiTurn $turn, TurnLease $lease, GuestContext $guest, string $text, EvidencePool $pool, array $actions = [], array $meta = []): void
    {
        if (! $pool->isEmpty()) {
            $verified = $this->verifiedPool($pool, $turn->locale);

            if ($verified === null) {
                $this->complete($turn, $lease, $guest, $this->noSourceText(SceneCode::tryFrom($turn->context_snapshot['scene'] ?? '') ?? SceneCode::Welcome), new EvidencePool, TurnCards::helpActions(), $meta);

                return;
            }
            $verified->noteUnverifiedExcluded($pool->unverifiedExcludedCount());
            $pool = $verified;
        }

        $this->completeWithCards($turn, $lease, $guest, $text, TurnCards::forPool($pool, $actions), $pool, $meta);
    }

    /**
     * @param  array{sources: list<array<string, mixed>>, ui: list<array<string, mixed>>, actions: list<array<string, mixed>>, operation?: ?array<string, mixed>}  $cards
     * @param  array<string, mixed>  $meta
     */
    private function completeWithCards(AiTurn $turn, TurnLease $lease, GuestContext $guest, string $text, array $cards, ?EvidencePool $pool, array $meta = []): void
    {
        $meta['usage'] = ($meta['usage'] ?? []) + $this->usageStats();

        if (! GuestSession::query()->whereKey($guest->guestSessionId)->exists()) {
            $this->fail($turn, 'session_expired', __('deck.assistant.error'));

            return;
        }

        if (! $lease->stillOwned()) {
            // Recovery took over; do not commit stale output.
            return;
        }

        TurnRecorder::finish($turn, TurnStatus::Completed, $text, $cards, $pool, null, $meta);
    }

    /**
     * @param  array<string, mixed>|null  $cards
     * @param  array<string, mixed>  $meta
     */
    private function fail(AiTurn $turn, string $code, string $text, ?array $cards = null, array $meta = [], ?EvidencePool $evidence = null): void
    {
        $meta['usage'] = ($meta['usage'] ?? []) + $this->usageStats();

        TurnRecorder::finish($turn, TurnStatus::Failed, $text, $cards ?? TurnCards::forPool(new EvidencePool, TurnCards::helpActions()), $evidence, $code, $meta);
    }

    /**
     * Loop counters stored with the token usage (for operations metrics).
     *
     * @return array<string, int>
     */
    private function usageStats(): array
    {
        return array_filter($this->stats);
    }
}
