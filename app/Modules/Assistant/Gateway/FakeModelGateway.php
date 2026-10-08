<?php

namespace App\Modules\Assistant\Gateway;

use Closure;

/**
 * Deterministic gateway for local development without a provider and for
 * Plan 8 tests. Queue results or exceptions; records every request.
 *
 *   app()->instance(ModelGateway::class, FakeModelGateway::sequence([...]));
 */
final class FakeModelGateway implements ModelGateway
{
    /** @var list<ChatCompletionRequest> */
    public array $requests = [];

    /**
     * @param  list<ChatCompletionResult|GatewayException|Closure(ChatCompletionRequest): ChatCompletionResult>  $queue
     */
    public function __construct(private array $queue = []) {}

    /**
     * @param  list<ChatCompletionResult|GatewayException|Closure(ChatCompletionRequest): ChatCompletionResult>  $queue
     */
    public static function sequence(array $queue): self
    {
        return new self($queue);
    }

    /**
     * Result whose content is the project answer JSON.
     *
     * @param  list<int>  $sourceRevisionIds
     * @param  list<int>  $sourceMenuItemIds
     */
    public static function answer(string $text, array $sourceRevisionIds = [], array $sourceMenuItemIds = []): ChatCompletionResult
    {
        return new ChatCompletionResult(
            text: json_encode(['text' => $text, 'source_menu_item_ids' => $sourceMenuItemIds, 'source_revision_ids' => $sourceRevisionIds], JSON_UNESCAPED_UNICODE),
            toolCalls: [],
            finishReason: 'stop',
            usage: ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            model: 'fake',
        );
    }

    public function available(): bool
    {
        return true;
    }

    public function providerName(): string
    {
        return 'fake';
    }

    public function complete(ChatCompletionRequest $request, TurnDeadline $deadline): ChatCompletionResult
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue) ?? new GatewayException(GatewayException::UNAVAILABLE, 'Fake queue is empty.');

        if ($next instanceof GatewayException) {
            throw $next;
        }

        return $next instanceof Closure ? $next($request) : $next;
    }
}
