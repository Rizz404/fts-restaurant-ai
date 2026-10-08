<?php

namespace App\Http\Requests\Api;

use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Experience\Support\RestaurantSettings;
use App\Support\Http\ApiException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Turn input (TECHNICAL_CONTRACTS). Context is limited to scene, menu_item_id,
 * request_public_id, and preferences (whitelisted codes only); identity never
 * comes from the request. Ownership of the context request and the existence
 * of the context dish are checked by AppendTurn. Invalid context values are a
 * 422 invalid_context.
 */
class SendMessageRequest extends FormRequest
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
        $maxParty = app(RestaurantSettings::class)->get()->maxPartySize();

        return [
            'client_turn_id' => ['required', 'uuid'],
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'locale' => ['required', Rule::in(config('restaurant.locales'))],
            'context' => ['required', 'array:scene,menu_item_id,request_public_id,preferences'],
            'context.scene' => ['required', Rule::in(SceneCode::values())],
            'context.menu_item_id' => ['present', 'nullable', 'integer', 'min:1'],
            'context.request_public_id' => ['present', 'nullable', 'string', 'uuid'],
            'context.preferences' => ['present', 'nullable', 'array:dietary_tags,exclude_allergens,party_size'],
            'context.preferences.dietary_tags' => ['nullable', 'array', 'max:'.count(config('restaurant.dietary_tags'))],
            'context.preferences.dietary_tags.*' => ['string', 'distinct', Rule::in(config('restaurant.dietary_tags'))],
            'context.preferences.exclude_allergens' => ['nullable', 'array', 'max:'.count(config('restaurant.allergens'))],
            'context.preferences.exclude_allergens.*' => ['string', 'distinct', Rule::in(config('restaurant.allergens'))],
            'context.preferences.party_size' => ['nullable', 'integer', 'min:1', 'max:'.$maxParty],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('message'))) {
            // Strip control characters (keep newlines/tabs) and trim.
            $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $this->input('message'));
            $this->merge(['message' => trim((string) $clean)]);
        }
    }

    /**
     * Context problems are reported as invalid_context, everything else as
     * a normal validation failure.
     */
    protected function failedValidation(Validator $validator): void
    {
        $fields = $validator->errors()->toArray();
        $contextOnly = $fields !== [] && collect(array_keys($fields))->every(fn (string $key) => str_starts_with($key, 'context'));

        if ($contextOnly) {
            throw new ApiException('invalid_context', 422, $fields);
        }

        parent::failedValidation($validator);
    }

    /**
     * @return array{client_turn_id: string, message: string, locale: string, context: array{scene: string, menu_item_id: ?int, request_public_id: ?string, preferences: ?array{dietary_tags: list<string>, exclude_allergens: list<string>, party_size: ?int}}}
     */
    public function turnInput(): array
    {
        $context = $this->validated('context');

        return [
            'client_turn_id' => strtolower($this->validated('client_turn_id')),
            'message' => $this->validated('message'),
            'locale' => $this->validated('locale'),
            'context' => [
                'scene' => $context['scene'],
                'menu_item_id' => isset($context['menu_item_id']) ? (int) $context['menu_item_id'] : null,
                'request_public_id' => isset($context['request_public_id']) ? strtolower($context['request_public_id']) : null,
                'preferences' => self::preferences($context['preferences'] ?? null),
            ],
        ];
    }

    /**
     * Sorted, deduplicated preferences; null when nothing is selected so the
     * turn fingerprint stays stable.
     *
     * @param  array<string, mixed>|null  $input
     * @return array{dietary_tags: list<string>, exclude_allergens: list<string>, party_size: ?int}|null
     */
    public static function preferences(?array $input): ?array
    {
        if ($input === null) {
            return null;
        }

        $diet = array_values(array_unique((array) ($input['dietary_tags'] ?? [])));
        $allergens = array_values(array_unique((array) ($input['exclude_allergens'] ?? [])));
        sort($diet);
        sort($allergens);
        $party = isset($input['party_size']) ? (int) $input['party_size'] : null;

        return $diet === [] && $allergens === [] && $party === null
            ? null
            : ['dietary_tags' => $diet, 'exclude_allergens' => $allergens, 'party_size' => $party];
    }
}
