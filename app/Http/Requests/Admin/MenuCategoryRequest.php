<?php

namespace App\Http\Requests\Admin;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MenuCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageCategories', MenuItem::class);
    }

    protected function prepareForValidation(): void
    {
        $merge = ['is_active' => $this->boolean('is_active')];

        if (is_string($this->input('code'))) {
            $merge['code'] = Str::slug($this->input('code'), '_');
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $creating = ! $category instanceof MenuCategory;

        $rules = [
            'code' => $creating
                ? ['required', 'string', 'max:40', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/', Rule::unique('menu_categories', 'code')]
                : ['exclude'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'names' => ['required', 'array'],
        ];

        foreach (config('restaurant.locales') as $locale) {
            $rules["names.{$locale}"] = ['required', 'string', 'max:120'];
        }

        return $rules;
    }

    /**
     * @return array{code?: string, sort_order: int, is_active: bool, names: array<string, string>}
     */
    public function payload(): array
    {
        $validated = $this->validated();
        $names = [];
        foreach (config('restaurant.locales') as $locale) {
            $names[$locale] = trim((string) $validated['names'][$locale]);
        }

        return array_filter(['code' => $validated['code'] ?? null], fn ($value) => $value !== null) + [
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'names' => $names,
        ];
    }

    public function attributes(): array
    {
        $attributes = ['code' => __('menu.fields.code'), 'sort_order' => __('menu.fields.sort_order')];

        foreach (config('restaurant.locale_names') as $locale => $name) {
            $attributes["names.{$locale}"] = __('menu.fields.name').' ('.$name.')';
        }

        return $attributes;
    }
}
