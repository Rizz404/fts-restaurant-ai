<?php

namespace App\Modules\Assistant\Gateway;

/**
 * Provider-neutral chat completion request.
 */
final class ChatCompletionRequest
{
    /**
     * @param  list<array<string, mixed>>  $messages  OpenAI-style role/content messages.
     * @param  list<array<string, mixed>>  $tools  Function definitions; empty until Plan 7.
     */
    public function __construct(
        public readonly array $messages,
        public readonly array $tools = [],
        public readonly ?float $temperature = null,
        public readonly ?int $maxTokens = null,
    ) {}
}
