<?php

namespace App\Http\Requests\Admin;

use App\Models\KnowledgeArticle;
use App\Modules\Knowledge\Validation\ArticleRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Create article (with first draft) and save draft share ArticleRules.
 */
class InfoArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article instanceof KnowledgeArticle
            ? $this->user()->can('update', $article)
            : $this->user()->can('create', KnowledgeArticle::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => Str::slug($this->input('slug'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $article = $this->route('article');

        return ArticleRules::rules($article instanceof KnowledgeArticle ? $article : null);
    }

    public function after(): array
    {
        return [fn (Validator $validator) => ArticleRules::validateLists($validator)];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return ArticleRules::payload($this->validated());
    }

    public function attributes(): array
    {
        $attributes = [
            'slug' => __('info.fields.slug'),
            'category' => __('info.fields.category'),
            'effective_from' => __('info.fields.effective_from'),
            'effective_until' => __('info.fields.effective_until'),
        ];

        foreach (config('restaurant.locale_names') as $locale => $name) {
            foreach (['title', 'body', 'tags', 'aliases'] as $field) {
                $attributes["translations.{$locale}.{$field}"] = __('info.fields.'.$field).' ('.$name.')';
            }
        }

        return $attributes;
    }
}
