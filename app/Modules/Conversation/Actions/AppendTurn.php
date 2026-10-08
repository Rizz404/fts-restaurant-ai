<?php

namespace App\Modules\Conversation\Actions;

use App\Models\AiTurn;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Conversation\Support\AssistantBudget;
use App\Modules\Conversation\Support\Fingerprint;
use App\Modules\Conversation\Support\TurnLease;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Menu\MenuRetriever;
use App\Modules\Requests\Support\RequestAccess;
use App\Support\Http\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Accepts a guest message as a new turn, or returns the existing turn for a
 * retried client_turn_id. The same key with a different payload is a 409.
 * Acquires the guest-session + conversation lease before persisting; the
 * caller must pass the lease to ExecuteTurn (which releases it). The daily
 * AI budget is counted in the same transaction as the turn.
 */
class AppendTurn
{
    public function __construct(
        private readonly MenuRetriever $menu,
        private readonly RecoverStaleTurn $recover,
    ) {}

    /**
     * @param  array{client_turn_id: string, message: string, locale: string, context: array{scene: string, menu_item_id: ?int, request_public_id: ?string, preferences: ?array<string, mixed>}}  $input
     * @return array{turn: AiTurn, created: bool, lease: ?TurnLease, menu_item_name: ?string, budget_exhausted: bool}
     */
    public function handle(GuestContext $guest, Conversation $conversation, array $input): array
    {
        $this->recover->forConversation($conversation);

        $fingerprint = Fingerprint::of([
            'conversation' => $conversation->public_id,
            'message' => $input['message'],
            'locale' => $input['locale'],
            'context' => $input['context'],
        ]);

        if ($existing = $this->existing($conversation, $input['client_turn_id'], $fingerprint)) {
            return ['turn' => $existing, 'created' => false, 'lease' => null, 'menu_item_name' => null, 'budget_exhausted' => false];
        }

        $menuItemName = null;
        if ($input['context']['menu_item_id'] !== null) {
            $item = $this->menu->findEvidence((int) $input['context']['menu_item_id'], $input['locale']);
            if ($item === null) {
                throw new ApiException('invalid_context', 422, ['context.menu_item_id' => [__('ui.errors.invalid_context')]]);
            }
            $menuItemName = $item->name;
        }

        // A request context is only accepted for the guest's own or granted request.
        if ($input['context']['request_public_id'] !== null
            && ! RequestAccess::guestCanView($guest, $input['context']['request_public_id'])) {
            throw new ApiException('invalid_context', 422, ['context.request_public_id' => [__('ui.errors.invalid_context')]]);
        }

        $lease = TurnLease::acquire($guest->guestSessionId, $conversation->id);
        if ($lease === null) {
            throw new ApiException('turn_in_progress', 409);
        }

        $budgetExhausted = false;

        try {
            $turn = DB::transaction(function () use ($guest, $conversation, $input, $fingerprint, &$budgetExhausted) {
                $now = CarbonImmutable::now();

                $turn = AiTurn::create([
                    'public_id' => (string) Str::uuid(),
                    'conversation_id' => $conversation->id,
                    'client_turn_id' => $input['client_turn_id'],
                    'request_fingerprint' => $fingerprint,
                    'status' => TurnStatus::Processing,
                    'locale' => $input['locale'],
                    'context_snapshot' => $input['context'],
                    'started_at' => $now,
                    'deadline_at' => $now->addSeconds((int) config('assistant.deadline_seconds')),
                    'prompt_version' => config('assistant.prompt_version'),
                ]);

                ConversationMessage::create([
                    'conversation_id' => $conversation->id,
                    'turn_id' => $turn->id,
                    'role' => 'user',
                    'text' => $input['message'],
                    'created_at' => $now,
                ]);

                // Locale is the latest one used; context is replaced (null clears).
                $sceneContext = [
                    'scene' => $input['context']['scene'],
                    'menu_item_id' => $input['context']['menu_item_id'],
                    'request_public_id' => $input['context']['request_public_id'],
                ];
                $changed = $conversation->scene_context !== $sceneContext;
                $conversation->forceFill([
                    'locale' => $input['locale'],
                    'scene_context' => $sceneContext,
                    'context_revision' => $conversation->context_revision + ($changed ? 1 : 0),
                    'last_activity_at' => $now,
                ])->save();

                $budgetExhausted = AssistantBudget::consume($guest->guestSessionId);

                return $turn;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same client_turn_id won the insert.
            $lease->release();
            $existing = $this->existing($conversation, $input['client_turn_id'], $fingerprint);

            return ['turn' => $existing ?? abort(409), 'created' => false, 'lease' => null, 'menu_item_name' => null, 'budget_exhausted' => false];
        } catch (\Throwable $e) {
            $lease->release();

            throw $e;
        }

        return ['turn' => $turn, 'created' => true, 'lease' => $lease, 'menu_item_name' => $menuItemName, 'budget_exhausted' => $budgetExhausted];
    }

    private function existing(Conversation $conversation, string $clientTurnId, string $fingerprint): ?AiTurn
    {
        $turn = AiTurn::query()
            ->where('conversation_id', $conversation->id)
            ->where('client_turn_id', $clientTurnId)
            ->first();

        if ($turn !== null && ! hash_equals($turn->request_fingerprint, $fingerprint)) {
            throw new ApiException('idempotency_conflict', 409);
        }

        if ($turn !== null) {
            $this->recover->ifStale($turn);
            $turn->refresh();
        }

        return $turn;
    }
}
