<?php

namespace App\Modules\Assistant\Gateway;

/**
 * Model provider boundary. Implementations never decide user permissions
 * and never retry a POST on their own.
 */
interface ModelGateway
{
    /**
     * @throws GatewayException
     */
    public function complete(ChatCompletionRequest $request, TurnDeadline $deadline): ChatCompletionResult;

    /**
     * Whether a provider is configured and the assistant is enabled.
     */
    public function available(): bool;

    /**
     * Short provider label for turn records (no secrets).
     */
    public function providerName(): string;
}
