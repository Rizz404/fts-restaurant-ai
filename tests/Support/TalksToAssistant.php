<?php

namespace Tests\Support;

use App\Modules\Assistant\Gateway\ChatCompletionResult;
use App\Modules\Assistant\Gateway\FakeModelGateway;
use App\Modules\Assistant\Gateway\ModelGateway;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Guest conversation helpers with a fake model (never a real provider). The
 * guest is whoever the test's HTTP session is; the first write creates the
 * guest session row.
 */
trait TalksToAssistant
{
    /**
     * @param  list<ChatCompletionResult|\Throwable|\Closure>  $queue
     */
    protected function fakeModel(array $queue): FakeModelGateway
    {
        $fake = FakeModelGateway::sequence($queue);
        $this->app->instance(ModelGateway::class, $fake);

        return $fake;
    }

    protected function toolsEnabled(bool $enabled): void
    {
        config(['assistant.tools.enabled' => $enabled, 'assistant.prompt_version' => $enabled ? 'restaurant-v2' : 'restaurant-v1']);
    }

    protected function startConversation(string $scene = 'menu', string $locale = 'id'): string
    {
        return $this->postJson('/api/v1/conversations', ['locale' => $locale, 'scene' => $scene])
            ->assertCreated()
            ->json('data.conversation_id');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function say(string $conversation, string $message, array $context = [], ?string $key = null, string $locale = 'id'): TestResponse
    {
        return $this->postJson("/api/v1/conversations/{$conversation}/messages", [
            'client_turn_id' => $key ?? (string) Str::uuid(),
            'message' => $message,
            'locale' => $locale,
            'context' => $context + ['scene' => 'menu', 'menu_item_id' => null, 'request_public_id' => null, 'preferences' => null],
        ]);
    }

    /**
     * A model response that calls one tool.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function toolCall(string $name, array $arguments = [], string $id = 'call_1'): ChatCompletionResult
    {
        return new ChatCompletionResult(
            text: null,
            toolCalls: [['id' => $id, 'name' => $name, 'arguments' => (string) json_encode($arguments === [] ? new \stdClass : $arguments)]],
            finishReason: 'tool_calls',
            usage: ['total_tokens' => 10],
            model: 'fake',
        );
    }

    /**
     * A final answer in the project JSON format.
     *
     * @param  list<int>  $menuIds
     * @param  list<int>  $revisionIds
     */
    protected function finalAnswer(string $text, array $menuIds = [], array $revisionIds = []): ChatCompletionResult
    {
        return FakeModelGateway::answer($text, $revisionIds, $menuIds);
    }

    /**
     * @return array<string, mixed>
     */
    protected function cardsOf(TestResponse $response, string $type): array
    {
        return collect($response->json('data.ui'))->firstWhere('type', $type) ?? [];
    }
}
