<?php

namespace App\Modules\Assistant\Tools;

use App\Models\ToolInvocation;
use App\Modules\Identity\Guest\GuestContext;
use App\Support\Http\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Runs one model tool call: whitelist lookup → decode JSON object → server
 * validation → handler. Every call is recorded in tool_invocations with
 * bounded input and a result reference (no raw arguments, no request
 * text or contact data). Domain errors become data for the model; unexpected
 * exceptions are logged and rethrown so the turn falls back.
 */
class ToolExecutor
{
    public function __construct(private readonly ToolRegistry $registry) {}

    /**
     * @param  array{id: string, name: string, arguments: string}  $call
     */
    public function run(GuestContext $guest, array $call, ToolContext $context): ToolResult
    {
        $started = CarbonImmutable::now();
        $name = mb_substr($call['name'], 0, 64);
        $tool = $this->registry->get($call['name']);

        if ($tool === null) {
            $result = ToolResult::error(ToolResult::UNKNOWN_TOOL, ['error' => 'unknown_tool']);
            $this->record($context, $call['id'], $name, $result, null, $started);

            return $result;
        }

        $input = null;

        try {
            $validated = $tool->validate($guest, ToolArguments::decode($call['arguments']));
            $input = $tool->loggableInput($validated);
            $result = $tool->execute($guest, $validated, $context);
        } catch (ToolArgumentException $e) {
            $result = ToolResult::error(ToolResult::VALIDATION_FAILED, ['fields' => $e->fields]);
        } catch (ApiException $e) {
            $result = ToolResult::error(match ($e->status) {
                403 => ToolResult::NOT_AUTHORIZED,
                404 => ToolResult::NOT_FOUND,
                409 => ToolResult::CONFLICT,
                default => ToolResult::VALIDATION_FAILED,
            }, ['error' => $e->errorCode, 'fields' => $e->fields]);
        } catch (\Throwable $e) {
            $this->record($context, $call['id'], $name, ToolResult::error('error'), $input, $started);

            throw $e;
        }

        $this->record($context, $call['id'], $name, $result, $input, $started);

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $input
     */
    private function record(ToolContext $context, string $callId, string $name, ToolResult $result, ?array $input, CarbonImmutable $started): void
    {
        $finished = CarbonImmutable::now();

        ToolInvocation::create([
            'turn_id' => $context->turn->id,
            'call_id' => mb_substr($callId, 0, 128),
            'name' => $name,
            'status' => $result->code,
            'validated_input' => $input,
            'result_reference' => $result->reference ?: null,
            'started_at' => $started,
            'finished_at' => $finished,
        ]);

        Log::info('assistant.tool', [
            'turn_id' => $context->turn->id,
            'tool' => $name,
            'status' => $result->code,
            'duration_ms' => (int) $started->diffInMilliseconds($finished),
            'operation' => $result->operation['draft_id'] ?? null,
        ]);
    }
}
