<?php

namespace App\Http\Requests\Api;

use App\Modules\Knowledge\Enums\KnowledgeCategory;
use App\Modules\Knowledge\KnowledgeRetriever;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InfoSearchRequest extends FormRequest
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
            'q' => ['required', 'string', 'min:1', 'max:'.KnowledgeRetriever::MAX_QUERY_LENGTH],
            'category' => ['nullable', Rule::in(KnowledgeCategory::values())],
            'locale' => ['nullable', Rule::in(config('restaurant.locales'))],
        ];
    }

    public function searchLocale(): string
    {
        return $this->validated('locale') ?? app()->getLocale();
    }
}
