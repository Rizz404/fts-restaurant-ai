<?php

namespace Tests\Feature;

use App\Modules\Assistant\Gateway\ChatCompletionRequest;
use App\Modules\Assistant\Gateway\CompatibleChatCompletionsGateway;
use App\Modules\Assistant\Gateway\GatewayException;
use App\Modules\Assistant\Gateway\TurnDeadline;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Plan 5/7: the OpenAI-compatible gateway wire format. No real provider.
 */
class ModelGatewayTest extends TestCase
{
    private const URL = 'https://llm.example.test/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'assistant.enabled' => true,
            'assistant.reasoning_effort' => null,
            'services.local_llm.base_url' => 'https://llm.example.test',
            'services.local_llm.api_key' => 'sk-test-secret',
            'services.local_llm.model' => 'test-model',
        ]);
    }

    private function gateway(): CompatibleChatCompletionsGateway
    {
        return new CompatibleChatCompletionsGateway;
    }

    private function request(array $tools = []): ChatCompletionRequest
    {
        return new ChatCompletionRequest([['role' => 'user', 'content' => 'Hi']], $tools);
    }

    private static function choice(array $message, string $finish = 'stop'): array
    {
        return ['model' => 'test-model', 'choices' => [['message' => $message, 'finish_reason' => $finish]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15]];
    }

    public function test_posts_exact_endpoint_and_payload_without_optional_fields(): void
    {
        Http::fake([self::URL => Http::response(self::choice(['role' => 'assistant', 'content' => '{"text":"ok","source_revision_ids":[]}']))]);

        $result = $this->gateway()->complete($this->request(), TurnDeadline::startingNow());

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === self::URL
                && $request->hasHeader('Authorization', 'Bearer sk-test-secret')
                && $body['model'] === 'test-model'
                && $body['temperature'] === 0.3
                && $body['max_tokens'] === 2048
                && ! array_key_exists('tools', $body)
                && ! array_key_exists('tool_choice', $body)
                && ! array_key_exists('reasoning_effort', $body);
        });
        $this->assertSame('stop', $result->finishReason);
        $this->assertSame(15, $result->usage['total_tokens']);
    }

    public function test_sends_tools_and_reasoning_effort_only_when_present(): void
    {
        config(['assistant.reasoning_effort' => 'none']);
        Http::fake([self::URL => Http::response(self::choice(['role' => 'assistant', 'content' => null, 'tool_calls' => [
            ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'list_my_requests', 'arguments' => '{}']],
        ]], 'tool_calls'))]);

        $tools = [['type' => 'function', 'function' => ['name' => 'list_my_requests', 'description' => 'x', 'parameters' => ['type' => 'object']]]];
        $result = $this->gateway()->complete($this->request($tools), TurnDeadline::startingNow());

        Http::assertSent(fn (Request $request) => $request['tools'] === $tools && $request['tool_choice'] === 'auto' && $request['reasoning_effort'] === 'none');
        $this->assertSame([['id' => 'call_1', 'name' => 'list_my_requests', 'arguments' => '{}']], $result->toolCalls);
    }

    public function test_base_url_with_v1_or_plain_http_in_production_is_rejected(): void
    {
        config(['services.local_llm.base_url' => 'https://llm.example.test/v1']);
        $this->assertFalse($this->gateway()->available());

        config(['services.local_llm.base_url' => 'http://llm.example.test']);
        $this->assertTrue($this->gateway()->available(), 'HTTP allowed in testing');

        $this->app['env'] = 'production';
        $this->assertFalse($this->gateway()->available());
    }

    public function test_disabled_assistant_is_unavailable_without_http(): void
    {
        config(['assistant.enabled' => false]);

        $this->assertGatewayError(GatewayException::UNAVAILABLE);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function failures(): array
    {
        return [
            '429' => [429, GatewayException::RATE_LIMITED],
            '500' => [500, GatewayException::UNAVAILABLE],
            '400' => [400, GatewayException::BAD_REQUEST],
        ];
    }

    #[DataProvider('failures')]
    public function test_provider_errors_map_to_codes_without_body(int $status, string $code): void
    {
        Http::fake([self::URL => Http::response(['error' => 'secret provider detail'], $status)]);

        try {
            $this->gateway()->complete($this->request(), TurnDeadline::startingNow());
            $this->fail('Expected GatewayException');
        } catch (GatewayException $e) {
            $this->assertSame($code, $e->errorCode);
            $this->assertStringNotContainsString('secret provider detail', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_malformed_response_is_bad_response(): void
    {
        Http::fake([self::URL => Http::response(['unexpected' => true])]);

        $this->assertGatewayError(GatewayException::BAD_RESPONSE);
    }

    private function assertGatewayError(string $code): void
    {
        try {
            $this->gateway()->complete($this->request(), TurnDeadline::startingNow());
            $this->fail("Expected {$code}");
        } catch (GatewayException $e) {
            $this->assertSame($code, $e->errorCode);
        }
    }

    public function test_expired_deadline_does_not_call_provider(): void
    {
        Http::fake();

        try {
            $this->gateway()->complete($this->request(), new TurnDeadline(now()->toImmutable()->subSecond()));
            $this->fail('Expected timeout');
        } catch (GatewayException $e) {
            $this->assertSame(GatewayException::TIMEOUT, $e->errorCode);
        }

        Http::assertNothingSent();
    }
}
