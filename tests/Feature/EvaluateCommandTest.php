<?php

namespace Tests\Feature;

use App\Modules\Identity\Guest\SessionGrants;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Plan 8: the evaluation command refuses to run where it could do harm, and
 * its synthetic corpus has the promised shape. The real provider is never called.
 */
class EvaluateCommandTest extends TestCase
{
    public function test_it_refuses_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('restaurant:eval')->expectsOutputToContain('only runs in local or testing')->assertFailed();
    }

    public function test_it_refuses_a_database_that_is_not_named_eval(): void
    {
        // The test database is not an evaluation database: it must never be rebuilt.
        $this->artisan('restaurant:eval')->expectsOutputToContain('must contain "eval"')->assertFailed();
    }

    public function test_the_corpus_has_thirty_scenarios_per_language_in_the_planned_categories(): void
    {
        $corpus = json_decode(File::get(base_path('docs/evals/corpus.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(90, $corpus);
        foreach (['id', 'en', 'ja'] as $locale) {
            $rows = collect($corpus)->where('locale', $locale);
            $this->assertCount(30, $rows, $locale);
            $this->assertSame(
                ['allergen' => 4, 'availability_status' => 3, 'draft' => 2, 'forbidden' => 5, 'info' => 4, 'menu' => 8, 'no_answer' => 4],
                $rows->countBy('category')->sortKeys()->all(),
                $locale,
            );
        }
        $this->assertCount(90, array_unique(array_column($corpus, 'id')), 'Scenario ids are unique.');
        $this->assertSame(array_fill(0, 90, true), array_map(fn ($row) => trim($row['message']) !== '', $corpus));
    }

    public function test_the_synthetic_menu_covers_verified_unverified_and_sold_out_dishes(): void
    {
        $menu = json_decode(File::get(base_path('docs/evals/menu.json')), true, flags: JSON_THROW_ON_ERROR);
        $dishes = collect($menu['dishes']);

        $this->assertCount(24, $dishes);
        $this->assertGreaterThanOrEqual(2, $dishes->whereNotNull('then_change_allergens')->count(), 'Unverified dishes exist.');
        $this->assertGreaterThanOrEqual(1, $dishes->where('available', false)->count(), 'A sold-out dish exists.');
        foreach ($dishes as $dish) {
            $this->assertStringStartsWith('[EVAL]', $dish['translations']['en']['name']);
            $this->assertSame(['en', 'id', 'ja'], collect($dish['translations'])->keys()->sort()->values()->all());
        }
    }

    public function test_session_grants_are_empty_outside_a_session_so_console_code_cannot_crash(): void
    {
        $grants = app(SessionGrants::class);

        $this->assertSame([], $grants->all());
        $this->assertFalse($grants->has('x'));
        $grants->add('x');
        $this->assertSame([], $grants->all());
    }
}
