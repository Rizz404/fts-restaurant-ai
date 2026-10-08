<?php

namespace App\Modules\Assistant\Gateway;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * OpenAI-compatible /v1/chat/completions client (same contract as the other
 * FTS AI projects). LOCAL_LLM_BASE_URL is an origin/base path without /v1; the endpoint is
 * appended exactly once. Optional capabilities (reasoning_effort) are sent
 * only when configured. No automatic retries.
 */
final class CompatibleChatCompletionsGateway implements ModelGateway
{
    private const ENDPOINT = '/v1/chat/completions';

    public function available(): bool
    {
        return config('assistant.enabled')
            && $this->baseUrl() !== null
            && filled(config('services.local_llm.model'));
    }

    public function providerName(): string
    {
        return 'local_llm';
    }

    public function complete(ChatCompletionRequest $request, TurnDeadline $deadline): ChatCompletionResult
    {
        $base = $this->baseUrl();
        if (! config('assistant.enabled') || $base === null || blank(config('services.local_llm.model'))) {
            throw new GatewayException(GatewayException::UNAVAILABLE, 'Assistant disabled or provider not configured.');
        }

        $remaining = $deadline->remainingSeconds();
        if ($remaining < 1) {
            throw new GatewayException(GatewayException::TIMEOUT, 'Turn deadline reached before calling the provider.');
        }

        $payload = [
            'model' => config('services.local_llm.model'),
            'messages' => $request->messages,
            'temperature' => $request->temperature ?? (float) config('assistant.temperature'),
            'max_tokens' => $request->maxTokens ?? (int) config('assistant.max_tokens'),
        ];

        if ($request->tools !== []) {
            $payload['tools'] = $request->tools;
            $payload['tool_choice'] = 'auto';
        }

        if (filled(config('assistant.reasoning_effort'))) {
            $payload['reasoning_effort'] = config('assistant.reasoning_effort');
        }

        $http = Http::acceptJson()
            ->asJson()
            ->connectTimeout((int) config('assistant.connect_timeout_seconds'))
            ->timeout(max(1, (int) floor($remaining)));

        if (filled(config('services.local_llm.api_key'))) {
            $http = $http->withToken((string) config('services.local_llm.api_key'));
        }

        $caBundle = config('services.local_llm.ca_bundle');
        if (filled($caBundle) && is_file($caBundle)) {
            $http = $http->withOptions(['verify' => $caBundle]);
        }

        try {
            $response = $http->post($base.self::ENDPOINT, $payload);
        } catch (ConnectionException $e) {
            $timedOut = Str::contains(Str::lower($e->getMessage()), ['timed out', 'timeout']);

            throw new GatewayException($timedOut ? GatewayException::TIMEOUT : GatewayException::UNAVAILABLE, 'Provider connection failed.');
        }

        if ($response->status() === 429) {
            throw new GatewayException(GatewayException::RATE_LIMITED, 'Provider rate limited the request.');
        }
        if ($response->serverError()) {
            throw new GatewayException(GatewayException::UNAVAILABLE, 'Provider returned HTTP '.$response->status().'.');
        }
        if ($response->clientError()) {
            throw new GatewayException(GatewayException::BAD_REQUEST, 'Provider rejected the request with HTTP '.$response->status().'.');
        }

        $json = $response->json();
        $choice = is_array($json) ? ($json['choices'][0] ?? null) : null;
        if (! is_array($choice) || ! is_array($choice['message'] ?? null)) {
            throw new GatewayException(GatewayException::BAD_RESPONSE, 'Provider response has no choices.');
        }

        $message = $choice['message'];
        $toolCalls = [];
        foreach ((array) ($message['tool_calls'] ?? []) as $call) {
            if (is_array($call) && isset($call['function']['name'])) {
                $toolCalls[] = [
                    'id' => (string) ($call['id'] ?? ''),
                    'name' => (string) $call['function']['name'],
                    'arguments' => is_string($call['function']['arguments'] ?? null) ? $call['function']['arguments'] : json_encode($call['function']['arguments'] ?? []),
                ];
            }
        }

        $usage = [];
        foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $key) {
            if (isset($json['usage'][$key]) && is_numeric($json['usage'][$key])) {
                $usage[$key] = (int) $json['usage'][$key];
            }
        }

        return new ChatCompletionResult(
            text: is_string($message['content'] ?? null) ? $message['content'] : null,
            toolCalls: $toolCalls,
            finishReason: is_string($choice['finish_reason'] ?? null) ? $choice['finish_reason'] : null,
            usage: $usage,
            model: is_string($json['model'] ?? null) ? Str::limit($json['model'], 150, '') : null,
        );
    }

    /**
     * Base URL without trailing slash; null when missing or misconfigured
     * (contains /v1 or /chat/completions, or plain HTTP outside local/testing).
     */
    private function baseUrl(): ?string
    {
        $base = rtrim(trim((string) config('services.local_llm.base_url')), '/');

        if ($base === '' || preg_match('#/(v1|chat/completions)(/|$)#', $base)) {
            return null;
        }

        if (str_starts_with($base, 'http://') && ! app()->environment(['local', 'testing'])) {
            return null;
        }

        return str_starts_with($base, 'http://') || str_starts_with($base, 'https://') ? $base : null;
    }
}
