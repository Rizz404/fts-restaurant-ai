<?php

namespace App\Modules\Assistant\Tools;

use App\Modules\Identity\Guest\GuestContext;

/**
 * One AI tool. Handlers are registered explicitly in ToolRegistry; the model
 * only picks a name from that whitelist. The guest identity always comes
 * from the server-side guest session, never from tool arguments.
 */
interface Tool
{
    public function name(): string;

    /**
     * Provider function definition (OpenAI-compatible "tools" entry).
     *
     * @return array{type: 'function', function: array{name: string, description: string, parameters: array<string, mixed>}}
     */
    public function definition(): array;

    /**
     * Server-side validation with the same domain rules as the forms.
     * Unknown keys are rejected.
     *
     * @param  array<string, mixed>  $arguments  Decoded JSON object from the model.
     * @return array<string, mixed>
     *
     * @throws ToolArgumentException
     */
    public function validate(GuestContext $guest, array $arguments): array;

    /**
     * @param  array<string, mixed>  $arguments  Output of validate().
     */
    public function execute(GuestContext $guest, array $arguments, ToolContext $context): ToolResult;

    /**
     * Bounded, non-sensitive view of the validated input for the invocation
     * log (e.g. a fingerprint instead of free text).
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function loggableInput(array $arguments): array;
}
