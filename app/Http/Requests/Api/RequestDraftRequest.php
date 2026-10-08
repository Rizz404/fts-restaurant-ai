<?php

namespace App\Http\Requests\Api;

use App\Modules\Requests\Enums\RequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create (POST) or edit (PATCH) a request draft. Type-specific validation
 * happens in RequestDraftRules (same rules as the AI tools); this class only
 * checks the envelope. Identity never comes from the body.
 */
class RequestDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'type' => [Rule::requiredIf($creating), Rule::prohibitedIf(! $creating), Rule::in(RequestType::values())],
            'payload' => ['required', 'array'],
            'contact_name' => ['nullable', 'string', 'max:80'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'contact_email' => ['nullable', 'string', 'max:255'],
            'conversation_public_id' => ['nullable', 'uuid'],
            'expected_revision' => [Rule::requiredIf(! $creating), 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{name: ?string, phone: ?string, email: ?string}
     */
    public function contact(): array
    {
        return [
            'name' => $this->input('contact_name'),
            'phone' => $this->input('contact_phone'),
            'email' => $this->input('contact_email'),
        ];
    }
}
