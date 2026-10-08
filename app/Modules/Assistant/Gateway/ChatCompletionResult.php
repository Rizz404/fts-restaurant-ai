<?php

namespace App\Modules\Assistant\Gateway;

final class ChatCompletionResult
{
    /**
     * @param  list<array{id: string, name: string, arguments: string}>  $toolCalls
     * @param  array<string, int>  $usage
     */
    public function __construct(
        public readonly ?string $text,
        public readonly array $toolCalls,
        public readonly ?string $finishReason,
        public readonly array $usage = [],
        public readonly ?string $model = null,
    ) {}
}
