<?php

namespace Tests\Feature;

use App\Modules\Menu\Actions\UpdateMenuItem;
use App\Modules\Menu\Actions\VerifyAllergens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 4 safety rules: allergen and diet claims exist only for dishes whose
 * data staff verified; a filter never lets an unverified dish through.
 */
class AllergenSafetyTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
    }

    /**
     * @return array<string, mixed>
     */
    private function updatePayload($dish, array $overrides = []): array
    {
        $dish->load(['translations', 'allergens', 'dietaryTags']);

        return array_replace([
            'category_id' => $dish->category_id,
            'price' => $dish->price,
            'is_available' => $dish->is_available,
            'is_chef_pick' => false,
            'spice_level' => null,
            'sort_order' => 1,
            'translations' => $dish->translations->mapWithKeys(fn ($t) => [$t->locale => ['name' => $t->name, 'description' => $t->description, 'aliases' => []]])->all(),
            'allergens' => $dish->allergens->mapWithKeys(fn ($row) => [$row->allergen_code => $row->relation->value])->all(),
            'dietary_tags' => $dish->dietaryTagCodes(),
        ], $overrides);
    }

    public function test_changing_allergens_clears_the_verification_of_a_published_dish(): void
    {
        $dish = $this->dish('tempe', 24000, ['soy' => 'contains'], ['vegan']);
        $this->assertTrue($dish->isVerified());

        app(UpdateMenuItem::class)->handle($this->adminForData(), $dish, $this->updatePayload($dish, ['allergens' => ['soy' => 'contains', 'peanuts' => 'may_contain']]));

        $this->assertFalse($dish->refresh()->isVerified());
        $this->assertTrue($dish->isPublished(), 'The dish stays published, only its claims disappear.');
    }

    public function test_changing_dietary_tags_also_clears_the_verification(): void
    {
        $dish = $this->dish('tempe', 24000, ['soy' => 'contains'], ['vegan']);

        app(UpdateMenuItem::class)->handle($this->adminForData(), $dish, $this->updatePayload($dish, ['dietary_tags' => ['vegan', 'halal']]));

        $this->assertFalse($dish->refresh()->isVerified());
    }

    public function test_an_edit_that_changes_neither_keeps_the_verification(): void
    {
        $dish = $this->dish('tempe', 24000, ['soy' => 'contains'], ['vegan']);

        app(UpdateMenuItem::class)->handle($this->adminForData(), $dish, $this->updatePayload($dish, ['price' => 26000]));

        $this->assertTrue($dish->refresh()->isVerified());
    }

    public function test_reverifying_restores_the_claims(): void
    {
        $dish = $this->unverifiedDish('soto', 38000, ['celery' => 'contains']);
        $this->assertFalse($dish->isVerified());

        app(VerifyAllergens::class)->handle($this->adminForData(), $dish);

        $this->getJson('/api/v1/menu/soto')->assertOk()->assertJsonPath('data.allergen_verified', true)->assertJsonPath('data.allergens.contains', ['celery']);
    }

    public function test_the_api_never_returns_allergen_or_diet_claims_for_an_unverified_dish(): void
    {
        $this->unverifiedDish('soto', 38000, ['celery' => 'contains', 'eggs' => 'may_contain'], ['halal']);

        $this->getJson('/api/v1/menu/soto')
            ->assertOk()
            ->assertJsonPath('data.allergen_verified', false)
            ->assertJsonPath('data.allergens', ['contains' => [], 'may_contain' => []])
            ->assertJsonPath('data.dietary_tags', []);

        $body = $this->getJson('/api/v1/menu')->assertOk()->getContent();
        $this->assertStringNotContainsString('celery', $body);
        $this->assertStringNotContainsString('halal', $body);
    }

    public function test_the_menu_page_warns_instead_of_claiming_for_an_unverified_dish(): void
    {
        $dish = $this->unverifiedDish('soto', 38000, ['celery' => 'contains'], ['halal']);

        $html = $this->get('/menu?lang=en')->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<article[^>]*data-menu-item="'.$dish->id.'".*?</article>#s', $html, $card), 'dish card not found');

        $this->assertStringContainsString('Allergen info not verified yet, please ask staff.', $card[0]);
        $this->assertStringNotContainsString('No allergens are listed for this dish.', $card[0]);
        $this->assertStringNotContainsString('Contains', $card[0]);
        $this->assertStringNotContainsString('Celery', $card[0]);
        $this->assertStringNotContainsString('Halal', $card[0]);

        $this->get('/menu/soto?lang=en')->assertOk()->assertSee('Allergen info not verified yet, please ask staff.');
    }

    public function test_a_verified_dish_without_allergens_says_none_are_listed_not_that_it_is_safe(): void
    {
        $this->dish('es-teh', 12000, [], ['vegan']);

        $this->get('/menu?lang=en')->assertOk()
            ->assertSee('No allergens are listed for this dish.')
            ->assertDontSee('allergen-free')
            ->assertDontSee('safe for');
    }

    public function test_exclude_allergens_removes_contains_may_contain_and_unverified_dishes(): void
    {
        $this->dish('gado-gado', 32000, ['peanuts' => 'contains'], ['vegetarian']);
        $this->dish('sate', 42000, ['soy' => 'contains', 'peanuts' => 'may_contain']);
        $this->dish('rendang', 68000, []);
        $this->dish('es-teh', 12000, []);
        $this->unverifiedDish('soto', 38000, ['celery' => 'contains']);
        $this->unverifiedDish('bebek', 62000, [], ['halal']);

        $response = $this->getJson('/api/v1/menu?exclude_allergens[]=peanuts')->assertOk();

        $this->assertEqualsCanonicalizing(['es-teh', 'rendang'], array_column($response->json('data'), 'slug'));
        $this->assertSame(2, $response->json('meta.unverified_excluded_count'));
    }

    public function test_a_diet_filter_only_returns_verified_dishes_with_the_tag(): void
    {
        $this->dish('gulai', 36000, [], ['vegan', 'vegetarian']);
        $this->dish('sate', 42000, ['soy' => 'contains'], ['halal']);
        $this->unverifiedDish('pepes', 34000, ['soy' => 'contains'], ['vegetarian']);

        $response = $this->getJson('/api/v1/menu?diet[]=vegetarian')->assertOk();

        $this->assertSame(['gulai'], array_column($response->json('data'), 'slug'));
        $this->assertSame(1, $response->json('meta.unverified_excluded_count'));
    }

    public function test_the_menu_page_reports_how_many_dishes_were_withheld(): void
    {
        $this->dish('rendang', 68000, []);
        $this->unverifiedDish('soto', 38000);

        $this->get('/menu?lang=en&exclude_allergens[]=peanuts')->assertOk()
            ->assertSee('1 dish is hidden because its allergen data is not verified.');
    }

    public function test_allergen_labels_exist_in_all_three_languages(): void
    {
        foreach (config('restaurant.allergens') as $code) {
            foreach (['id', 'en', 'ja'] as $locale) {
                $this->assertTrue(trans()->hasForLocale("menu.allergen.{$code}", $locale), "{$code} missing in {$locale}");
            }
        }
        foreach (config('restaurant.dietary_tags') as $tag) {
            foreach (['id', 'en', 'ja'] as $locale) {
                $this->assertTrue(trans()->hasForLocale("menu.diet.{$tag}", $locale), "{$tag} missing in {$locale}");
            }
        }
    }
}
