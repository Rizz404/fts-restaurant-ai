<?php

namespace App\Http\Requests\Admin;

use App\Models\MenuItem;
use App\Modules\Menu\Validation\MenuItemRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Create and update a dish; both share MenuItemRules with the actions.
 */
class MenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof MenuItem
            ? $this->user()->can('update', $item)
            : $this->user()->can('create', MenuItem::class);
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            // Unchecked boxes are absent from a form post.
            'is_available' => $this->boolean('is_available'),
            'is_chef_pick' => $this->boolean('is_chef_pick'),
            'remove_photo' => $this->boolean('remove_photo'),
        ];

        if (is_string($this->input('slug'))) {
            $merge['slug'] = Str::slug($this->input('slug'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $item = $this->route('item');

        return MenuItemRules::rules($item instanceof MenuItem ? $item : null);
    }

    public function after(): array
    {
        return [fn (Validator $validator) => MenuItemRules::validateExtras($validator)];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return MenuItemRules::payload($this->validated());
    }

    public function attributes(): array
    {
        $attributes = [
            'slug' => __('menu.fields.slug'),
            'category_id' => __('menu.fields.category'),
            'price' => __('menu.fields.price'),
            'spice_level' => __('menu.fields.spice_level'),
            'sort_order' => __('menu.fields.sort_order'),
            'photo' => __('menu.fields.photo'),
            'allergens' => __('menu.fields.allergens'),
            'dietary_tags' => __('menu.fields.dietary_tags'),
        ];

        foreach (config('restaurant.locale_names') as $locale => $name) {
            foreach (['name', 'description', 'aliases'] as $field) {
                $attributes["translations.{$locale}.{$field}"] = __('menu.fields.'.$field).' ('.$name.')';
            }
        }

        return $attributes;
    }
}
