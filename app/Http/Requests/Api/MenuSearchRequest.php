<?php

namespace App\Http\Requests\Api;

use App\Modules\Menu\Data\MenuFilters;
use App\Modules\Menu\MenuRetriever;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuSearchRequest extends FormRequest
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
        return [
            'q' => ['nullable', 'string', 'max:'.MenuFilters::MAX_QUERY_LENGTH],
            'category' => ['nullable', 'string', 'max:40'],
            'diet' => ['array'],
            'diet.*' => ['string', Rule::in(config('restaurant.dietary_tags'))],
            'exclude_allergens' => ['array'],
            'exclude_allergens.*' => ['string', Rule::in(config('restaurant.allergens'))],
            'max_price' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.MenuRetriever::MAX_RESULTS],
            'locale' => ['nullable', Rule::in(config('restaurant.locales'))],
        ];
    }

    public function filters(): MenuFilters
    {
        $data = $this->validated();

        return MenuFilters::fromArray([
            'query' => $data['q'] ?? null,
            'category' => $data['category'] ?? null,
            'dietary_tags' => $data['diet'] ?? [],
            'exclude_allergens' => $data['exclude_allergens'] ?? [],
            'max_price' => $data['max_price'] ?? null,
        ]);
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? MenuRetriever::MAX_RESULTS);
    }

    public function searchLocale(): string
    {
        return $this->validated('locale') ?? app()->getLocale();
    }
}
