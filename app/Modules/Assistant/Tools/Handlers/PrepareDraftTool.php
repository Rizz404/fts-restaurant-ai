<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Models\RequestDraft;
use App\Modules\Assistant\Tools\ToolArgumentException;
use App\Modules\Assistant\Tools\ToolArguments;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Conversation\Support\Fingerprint;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Requests\Actions\PrepareRequestDraft;
use App\Modules\Requests\Enums\RequestType;
use App\Modules\Requests\Presenters\RequestCards;
use App\Modules\Requests\Validation\RequestDraftRules;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Base of the three prepare_* tools. A prepare tool only saves a reviewable
 * draft; it never creates a request, never receives contact details (the
 * guest enters those in the review step), and the same domain rules as the
 * forms apply (RequestDraftRules). At most one draft per turn: a repeat call
 * with the same content returns the first draft, different content answers
 * with a conflict that points at the existing draft.
 */
abstract class PrepareDraftTool extends BaseTool
{
    public function __construct(protected readonly PrepareRequestDraft $prepare) {}

    /**
     * @param  array<string, mixed>  $arguments  Schema-validated arguments.
     */
    abstract protected function requestType(array $arguments): RequestType;

    /**
     * @param  array<string, mixed>  $arguments  Schema-validated arguments.
     * @return array<string, mixed>
     */
    abstract protected function payload(array $arguments): array;

    /**
     * Schema rules of the tool's own arguments (unknown keys are rejected).
     *
     * @return array<string, mixed>
     */
    abstract protected function rules(): array;

    final public function validate(GuestContext $guest, array $arguments): array
    {
        $validated = ToolArguments::validate($arguments, $this->rules());
        $this->afterSchema($validated);

        try {
            RequestDraftRules::validate($this->requestType($validated), $this->payload($validated));
        } catch (ValidationException $e) {
            throw $this->asArgumentError($e);
        }

        return $validated;
    }

    private function asArgumentError(ValidationException $e): ToolArgumentException
    {
        $fields = [];
        foreach ($e->errors() as $key => $messages) {
            $fields[(string) preg_replace('/^payload\./', '', $key)] = array_values($messages);
        }

        return new ToolArgumentException($fields);
    }

    /**
     * Cross-field checks that the per-field rules cannot express.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws ToolArgumentException
     */
    protected function afterSchema(array $validated): void {}

    final public function execute(GuestContext $guest, array $arguments, ToolContext $context): ToolResult
    {
        $type = $this->requestType($arguments);
        $turnId = $context->turn->id;

        try {
            $normalized = RequestDraftRules::validate($type, $this->payload($arguments))['payload'];
            $existing = RequestDraft::query()->where('originating_turn_id', $turnId)->first();

            if ($existing === null) {
                try {
                    $existing = $this->prepare->handle($guest, $type, $this->payload($arguments), [], $context->conversation->public_id, $turnId);
                } catch (UniqueConstraintViolationException) {
                    // Two calls raced for the same turn: the first draft wins.
                    $existing = RequestDraft::query()->where('originating_turn_id', $turnId)->firstOrFail();
                }
            }
        } catch (ValidationException $e) {
            throw $this->asArgumentError($e);
        }

        $same = $existing->type === $type && Fingerprint::of($existing->payload) === Fingerprint::of($normalized);

        return $this->result($existing, $same);
    }

    private function result(RequestDraft $draft, bool $same): ToolResult
    {
        $draft->loadMissing('team');
        $card = RequestCards::draft($draft);

        return new ToolResult(
            $same ? ToolResult::OK : ToolResult::CONFLICT,
            $same
                ? ['draft_id' => $draft->public_id, 'summary' => $card['summary'], 'note' => 'A draft was saved. The guest must review it, add contact details, and press Confirm. Nothing was sent.']
                : ['error' => 'draft_already_prepared', 'draft_id' => $draft->public_id, 'summary' => $card['summary']],
            ui: [$card],
            actions: [RequestCards::reviewAction($draft)],
            operation: RequestCards::operation($draft),
            reference: ['draft_id' => $draft->public_id, 'revision' => $draft->revision],
            terminal: true,
            text: __('deck.assistant.tools.draft_ready', ['type' => mb_strtolower($draft->type->label())]),
        );
    }

    /**
     * Free text never goes to the log; only structure and a fingerprint.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function loggableInput(array $arguments): array
    {
        return [
            'type' => $this->requestType($arguments)->value,
            'fields' => array_keys($arguments),
            'fingerprint' => Fingerprint::of($arguments),
        ];
    }
}
