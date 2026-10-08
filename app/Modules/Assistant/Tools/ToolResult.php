<?php

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Evidence\EvidencePool;

/**
 * Outcome of one tool call.
 *
 * - code: ok or a domain error the model may react to (validation_failed,
 *   not_authorized, not_found, conflict, unknown_tool).
 * - data: typed result sent back to the model as the tool message.
 * - evidence: dishes and info sources this call made available to the turn.
 * - ui/actions: safe server-built descriptors (never sent to the model).
 * - operation: draft/request reference created by the server, if any.
 * - reference: what the invocation log stores instead of the full result.
 * - terminal: the turn ends with this result; text is the server-built
 *   sentence in the turn locale (no extra completion is needed).
 */
final class ToolResult
{
    public const OK = 'ok';

    public const VALIDATION_FAILED = 'validation_failed';

    public const NOT_AUTHORIZED = 'not_authorized';

    public const NOT_FOUND = 'not_found';

    public const CONFLICT = 'conflict';

    public const UNKNOWN_TOOL = 'unknown_tool';

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $ui
     * @param  list<array<string, mixed>>  $actions
     * @param  array<string, mixed>|null  $operation
     * @param  array<string, mixed>  $reference
     */
    public function __construct(
        public readonly string $code,
        public readonly array $data = [],
        public readonly ?EvidencePool $evidence = null,
        public readonly array $ui = [],
        public readonly array $actions = [],
        public readonly ?array $operation = null,
        public readonly array $reference = [],
        public readonly bool $terminal = false,
        public readonly ?string $text = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $reference
     */
    public static function error(string $code, array $data = [], array $reference = []): self
    {
        return new self($code, $data, reference: $reference);
    }

    public function ok(): bool
    {
        return $this->code === self::OK;
    }

    /**
     * Tool message content for the provider (JSON object).
     */
    public function forModel(): string
    {
        return (string) json_encode(['status' => $this->code] + $this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
