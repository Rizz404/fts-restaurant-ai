<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Modules\Assistant\Tools\ToolArgumentException;
use App\Modules\Requests\Enums\RequestType;

/**
 * Draft of feedback or a special request (celebration setup, accessibility,
 * large groups). Feedback may stay anonymous; a special request needs a
 * contact at confirmation, entered by the guest in the review step.
 */
class PrepareFeedbackTool extends PrepareDraftTool
{
    public function name(): string
    {
        return 'prepare_feedback';
    }

    protected function description(): string
    {
        return 'Prepare a DRAFT of feedback (compliment, complaint, suggestion) or a special request (type special_request) from what the guest wrote. It is not sent: the guest reviews it and confirms with a button. Do not ask for a name or phone in the chat.';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['feedback', 'special_request']],
                'kind' => ['type' => 'string', 'enum' => ['compliment', 'complaint', 'suggestion'], 'description' => 'Required for type feedback.'],
                'subject' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 160],
                'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 5000, 'description' => 'At most 2000 characters for a special request.'],
                'date' => ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$', 'description' => 'Optional date for a special request.'],
            ],
            'required' => ['type', 'subject', 'body'],
        ];
    }

    protected function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:feedback,special_request'],
            'kind' => ['sometimes', 'nullable', 'string', 'in:compliment,complaint,suggestion'],
            'subject' => ['required', 'string', 'min:1', 'max:160'],
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'date' => ['sometimes', 'nullable', 'string', 'date_format:Y-m-d'],
        ];
    }

    protected function afterSchema(array $validated): void
    {
        if ($validated['type'] === 'feedback' && empty($validated['kind'])) {
            throw new ToolArgumentException(['kind' => ['kind is required for feedback']]);
        }
        if ($validated['type'] === 'special_request' && ! empty($validated['kind'])) {
            throw new ToolArgumentException(['kind' => ['kind is only for feedback']]);
        }
        if ($validated['type'] === 'feedback' && ! empty($validated['date'])) {
            throw new ToolArgumentException(['date' => ['date is only for special_request']]);
        }
    }

    protected function requestType(array $arguments): RequestType
    {
        return $arguments['type'] === 'special_request' ? RequestType::SpecialRequest : RequestType::Feedback;
    }

    protected function payload(array $arguments): array
    {
        if ($arguments['type'] === 'special_request') {
            return ['subject' => $arguments['subject'], 'body' => $arguments['body'], 'date' => $arguments['date'] ?? null];
        }

        return ['kind' => $arguments['kind'], 'subject' => $arguments['subject'], 'body' => $arguments['body'], 'related_reference' => null];
    }
}
