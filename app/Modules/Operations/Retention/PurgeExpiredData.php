<?php

namespace App\Modules\Operations\Retention;

use App\Models\AuditEvent;
use App\Models\Conversation;
use App\Models\GuestRequest;
use App\Models\GuestSession;
use App\Models\OperationReceipt;
use App\Models\RequestDraft;
use App\Models\RequestMessage;
use App\Models\ReservationSlotUsage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Retention baseline (PLAN_08), in batches of 200 with short transactions:
 *
 * - closed requests (terminal_at older than request_days): the request with
 *   its messages and events (cascade), receipts, notifications, and then its
 *   saved draft. The draft/request FK is circular and restricts, so the
 *   request always goes first. Guest contact data lives on the request row
 *   and disappears with it. pending/confirmed requests are never purged,
 *   whatever their age.
 * - unconsumed drafts: draft_grace_hours after expires_at.
 * - conversations: conversation_days after last activity (turns, messages,
 *   evidence, and tool invocations cascade; requests and drafts keep their
 *   rows, their conversation link is null-on-delete).
 * - guest sessions: guest_session_days after last_seen_at. Their conversations
 *   and usage rows cascade; requests and drafts keep their rows with a null
 *   guest_session_id.
 * - reservation_slot_usage older than 60 days, assistant_usage older than 90
 *   days, audit events, and receipts past their retention.
 *
 * The order is requests → drafts → conversations → sessions so nothing is
 * deleted while a restricting row still points at it. Users are never
 * deleted here. Counts only; no personal data is returned or logged.
 */
class PurgeExpiredData
{
    public const BATCH = 200;

    public const SLOT_USAGE_DAYS = 60;

    public const ASSISTANT_USAGE_DAYS = 90;

    /**
     * @return array<string, int>
     */
    public function counts(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return [
            'closed_requests' => $this->expiredRequests($now)->count(),
            'unconsumed_drafts' => $this->expiredDrafts($now)->count(),
            'conversations' => $this->expiredConversations($now)->count(),
            'guest_sessions' => $this->expiredSessions($now)->count(),
            'slot_usage' => $this->expiredSlotUsage($now)->count(),
            'assistant_usage' => $this->expiredAssistantUsage($now)->count(),
            'audit_events' => $this->expiredAudit($now)->count(),
            'receipts' => $this->expiredReceipts($now)->count(),
        ];
    }

    /**
     * @return array<string, int> Rows deleted per category.
     */
    public function run(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return [
            'closed_requests' => $this->inBatches($this->expiredRequests($now), fn (array $ids) => $this->deleteRequests($ids, $now)),
            'unconsumed_drafts' => $this->inBatches($this->expiredDrafts($now), fn (array $ids) => RequestDraft::query()->whereKey($ids)->delete()),
            'conversations' => $this->inBatches($this->expiredConversations($now), fn (array $ids) => Conversation::query()->whereKey($ids)->delete()),
            'guest_sessions' => $this->inBatches($this->expiredSessions($now), fn (array $ids) => GuestSession::query()->whereKey($ids)->delete()),
            'slot_usage' => $this->inBatches($this->expiredSlotUsage($now), fn (array $ids) => ReservationSlotUsage::query()->whereKey($ids)->delete()),
            'assistant_usage' => $this->inBatches($this->expiredAssistantUsage($now), fn (array $ids) => DB::table('assistant_usage')->whereIn('id', $ids)->delete()),
            'audit_events' => $this->inBatches($this->expiredAudit($now), fn (array $ids) => AuditEvent::query()->whereKey($ids)->delete()),
            'receipts' => $this->inBatches($this->expiredReceipts($now), fn (array $ids) => OperationReceipt::query()->whereKey($ids)->delete()),
        ];
    }

    /**
     * Deletes closed requests with everything that belongs to them. A request
     * that is no longer terminal (or was already removed) is skipped.
     *
     * @param  list<int>  $requestIds
     */
    private function deleteRequests(array $requestIds, CarbonImmutable $now): int
    {
        return DB::transaction(function () use ($requestIds, $now) {
            $requests = GuestRequest::query()
                ->whereKey($requestIds)
                ->whereNotNull('terminal_at')
                ->where('terminal_at', '<', $this->requestCutoff($now))
                ->lockForUpdate()
                ->get(['id', 'public_id', 'draft_id']);

            if ($requests->isEmpty()) {
                return 0;
            }

            $ids = $requests->pluck('id')->all();
            $publicIds = $requests->pluck('public_id')->all();
            $draftIds = $requests->pluck('draft_id')->filter()->all();
            $messageIds = RequestMessage::query()->whereIn('request_id', $ids)->pluck('id')->map(fn ($id) => (string) $id)->all();

            OperationReceipt::query()
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $q) => $q->where('resource_type', 'request')->whereIn('resource_public_id', $publicIds))
                    ->orWhere(fn (Builder $q) => $q->where('resource_type', 'request_message')->whereIn('resource_public_id', $messageIds ?: ['-'])))
                ->delete();

            DB::table('notifications')
                ->where(function ($query) use ($publicIds) {
                    foreach ($publicIds as $publicId) {
                        $query->orWhere('data', 'like', '%"request_public_id":"'.$publicId.'"%');
                    }
                })
                ->delete();

            // Messages and events cascade. The draft link restricts, so the
            // request goes first and the saved drafts after.
            $deleted = GuestRequest::query()->whereKey($ids)->delete();
            RequestDraft::query()->whereKey($draftIds)->delete();

            return $deleted;
        });
    }

    /**
     * Deletes matching ids in batches; each batch is its own short operation.
     *
     * @param  Builder<Model>|\Illuminate\Database\Query\Builder  $query
     * @param  callable(list<int>): int  $delete
     */
    private function inBatches(Builder|\Illuminate\Database\Query\Builder $query, callable $delete): int
    {
        $total = 0;

        do {
            $ids = (clone $query)->orderBy('id')->limit(self::BATCH)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($ids === []) {
                break;
            }

            $deleted = $delete($ids);
            $total += $deleted;
        } while (count($ids) === self::BATCH && $deleted > 0);

        return $total;
    }

    private function requestCutoff(CarbonImmutable $now): CarbonImmutable
    {
        return $now->subDays((int) config('restaurant.retention.request_days'));
    }

    /**
     * @return Builder<GuestRequest>
     */
    private function expiredRequests(CarbonImmutable $now): Builder
    {
        return GuestRequest::query()
            ->whereNotNull('terminal_at')
            ->where('terminal_at', '<', $this->requestCutoff($now));
    }

    /**
     * Drafts that never became a request and have been expired for the grace period.
     *
     * @return Builder<RequestDraft>
     */
    private function expiredDrafts(CarbonImmutable $now): Builder
    {
        return RequestDraft::query()
            ->whereNull('consumed_request_id')
            ->where('expires_at', '<', $now->subHours((int) config('restaurant.retention.draft_grace_hours')))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('guest_requests')->whereColumn('guest_requests.draft_id', 'request_drafts.id'));
    }

    /**
     * @return Builder<Conversation>
     */
    private function expiredConversations(CarbonImmutable $now): Builder
    {
        return Conversation::query()->where('last_activity_at', '<', $now->subDays((int) config('restaurant.retention.conversation_days')));
    }

    /**
     * @return Builder<GuestSession>
     */
    private function expiredSessions(CarbonImmutable $now): Builder
    {
        return GuestSession::query()->where('last_seen_at', '<', $now->subDays((int) config('restaurant.retention.guest_session_days')));
    }

    /**
     * @return Builder<ReservationSlotUsage>
     */
    private function expiredSlotUsage(CarbonImmutable $now): Builder
    {
        return ReservationSlotUsage::query()->where('date', '<', $now->subDays(self::SLOT_USAGE_DAYS)->format('Y-m-d'));
    }

    private function expiredAssistantUsage(CarbonImmutable $now): \Illuminate\Database\Query\Builder
    {
        return DB::table('assistant_usage')->where('usage_date', '<', $now->subDays(self::ASSISTANT_USAGE_DAYS)->format('Y-m-d'));
    }

    /**
     * @return Builder<AuditEvent>
     */
    private function expiredAudit(CarbonImmutable $now): Builder
    {
        return AuditEvent::query()->where('occurred_at', '<', $now->subDays((int) config('restaurant.retention.audit_days')));
    }

    /**
     * @return Builder<OperationReceipt>
     */
    private function expiredReceipts(CarbonImmutable $now): Builder
    {
        return OperationReceipt::query()->where('created_at', '<', $now->subDays((int) config('restaurant.retention.receipt_days')));
    }
}
