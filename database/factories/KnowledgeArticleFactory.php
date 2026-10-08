<?php

namespace Database\Factories;

use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Creates the article shell only; use the Knowledge actions (or
 * KnowledgeDemoSeeder) to create revisions and translations.
 *
 * @extends Factory<KnowledgeArticle>
 */
class KnowledgeArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => Str::slug(fake()->unique()->words(3, true)),
            'created_by' => User::factory(),
        ];
    }
}
