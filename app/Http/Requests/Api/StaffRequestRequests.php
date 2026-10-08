<?php

namespace App\Http\Requests\Api;

use App\Modules\Requests\Enums\MessageVisibility;
use App\Modules\Requests\Enums\RequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff write endpoints share this request class; the rule set is chosen by
 * the route name. Authorization (team membership) is enforced by the actions.
 */
class StaffRequestRequests extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['text', 'reason'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match (true) {
            $this->routeIs('*assignment*') => [
                'assignee_id' => ['required', 'integer', 'min:1'],
                'expected_version' => ['required', 'integer', 'min:1'],
                'idempotency_key' => ['required', 'uuid'],
            ],
            $this->routeIs('*status*') => [
                'status' => ['required', Rule::in(RequestStatus::values())],
                'expected_version' => ['required', 'integer', 'min:1'],
                'reason' => ['nullable', 'string', 'max:500'],
                'idempotency_key' => ['required', 'uuid'],
            ],
            default => [
                'text' => ['required', 'string', 'min:1', 'max:5000'],
                'visibility' => ['required', Rule::in(array_map(fn (MessageVisibility $v) => $v->value, MessageVisibility::cases()))],
                'idempotency_key' => ['required', 'uuid'],
            ],
        };
    }
}
