<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\MenuItem;
use App\Modules\Menu\Actions\ArchiveMenuItem;
use App\Modules\Menu\Actions\CreateMenuItem;
use App\Modules\Menu\Actions\PublishMenuItem;
use App\Modules\Menu\Actions\SetAvailability;
use App\Modules\Menu\Actions\UpdateMenuItem;
use App\Modules\Menu\Actions\VerifyAllergens;
use App\Modules\Menu\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 4: dish drafts, publication rules, archive, who may do what, slug
 * locking, audit, and price formatting per locale.
 */
class MenuPublicationTest extends TestCase
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
    private function payload(array $overrides = []): array
    {
        $names = ['id' => 'Soto', 'en' => 'Soto', 'ja' => 'ソト'];

        return array_replace([
            'slug' => 'soto-baru',
            'category_id' => $this->category()->id,
            'price' => 35000,
            'is_available' => true,
            'is_chef_pick' => false,
            'spice_level' => 1,
            'sort_order' => 1,
            'translations' => collect($names)->map(fn ($name) => ['name' => $name, 'description' => "Deskripsi {$name}", 'aliases' => []])->all(),
            'allergens' => ['celery' => 'contains'],
            'dietary_tags' => ['halal'],
        ], $overrides);
    }

    public function test_an_incomplete_draft_cannot_be_published(): void
    {
        $admin = $this->admin();
        $item = app(CreateMenuItem::class)->handle($admin, $this->payload([
            'price' => 0,
            'translations' => ['id' => ['name' => 'Soto', 'description' => '', 'aliases' => []], 'en' => ['name' => '', 'description' => '', 'aliases' => []], 'ja' => ['name' => '', 'description' => '', 'aliases' => []]],
        ]));

        try {
            app(PublishMenuItem::class)->handle($admin, $item);
            $this->fail('Publishing an incomplete dish must fail.');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(
                ['translations.id.description', 'translations.en.name', 'translations.en.description', 'translations.ja.name', 'translations.ja.description', 'price', 'allergen_verification'],
                array_keys($e->errors()),
            );
        }

        $this->assertFalse($item->refresh()->isPublished());
        $this->get('/menu/soto-baru')->assertNotFound();
    }

    public function test_a_complete_verified_dish_publishes_and_appears_publicly(): void
    {
        $admin = $this->admin();
        $item = app(CreateMenuItem::class)->handle($admin, $this->payload());
        app(VerifyAllergens::class)->handle($admin, $item);
        app(PublishMenuItem::class)->handle($admin, $item);

        $this->get('/menu')->assertOk()->assertSee('Soto');
        $this->get('/menu/soto-baru')->assertOk()->assertSee('35.000');
        $this->getJson('/api/v1/menu/soto-baru')->assertOk()->assertJsonPath('data.name', 'Soto');
        $this->assertTrue(AuditEvent::query()->where('action', 'menu.published')->exists());
    }

    public function test_publishing_twice_is_harmless(): void
    {
        $dish = $this->dish('nasi-uduk', 30000);
        $admin = $this->adminForData();

        app(PublishMenuItem::class)->handle($admin, $dish);
        app(PublishMenuItem::class)->handle($admin, $dish);

        $this->assertSame(1, AuditEvent::query()->where('action', 'menu.published')->where('subject_id', (string) $dish->id)->count());
    }

    public function test_archiving_hides_the_dish_everywhere(): void
    {
        $dish = $this->dish('gado-gado', 32000);

        app(ArchiveMenuItem::class)->handle($this->adminForData(), $dish);

        $this->get('/menu/gado-gado')->assertNotFound();
        $this->getJson('/api/v1/menu/gado-gado')->assertNotFound();
        $this->getJson('/api/v1/menu')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_editor_manages_dishes_and_staff_only_toggle_availability(): void
    {
        $dish = $this->dish('sate', 40000);
        $editor = $this->editor();
        $staff = $this->staff('kitchen');

        app(UpdateMenuItem::class)->handle($editor, $dish->refresh(), $this->payload(['slug' => 'sate', 'price' => 41000]));
        $this->assertSame(41000, $dish->refresh()->price);

        $this->expectException(AuthorizationException::class);
        try {
            app(SetAvailability::class)->handle($staff, $dish, false);
        } finally {
            $this->assertFalse($dish->refresh()->is_available);
        }

        app(UpdateMenuItem::class)->handle($staff, $dish, $this->payload());
    }

    public function test_staff_availability_endpoint_and_forbidden_routes(): void
    {
        $dish = $this->dish('sate', 40000);
        $staff = $this->staff('kitchen');

        $this->actingAs($staff)->get('/admin/menu')->assertOk();
        $this->actingAs($staff)->patch("/admin/menu/{$dish->id}/availability", ['is_available' => '0'])->assertRedirect();
        $this->assertFalse($dish->refresh()->is_available);

        $this->actingAs($staff)->get("/admin/menu/{$dish->id}/edit")->assertForbidden();
        $this->actingAs($staff)->post("/admin/menu/{$dish->id}/publication")->assertForbidden();
        $this->actingAs($staff)->post("/admin/menu/{$dish->id}/allergen-verification")->assertForbidden();
        $this->actingAs($staff)->get('/admin/menu/categories')->assertForbidden();
    }

    public function test_a_sold_out_dish_stays_visible_and_is_marked(): void
    {
        $this->dish('es-teler', 28000, options: ['available' => false]);

        $this->get('/menu')->assertOk()->assertSee('Habis');
        $this->getJson('/api/v1/menu/es-teler')->assertOk()->assertJsonPath('data.is_available', false);
    }

    public function test_the_slug_is_locked_after_the_first_publication(): void
    {
        $dish = $this->dish('rendang', 68000);
        $this->assertTrue($dish->slugIsLocked());

        $admin = $this->adminForData();
        app(UpdateMenuItem::class)->handle($admin, $dish->refresh(), $this->payload(['slug' => 'rendang-baru']));

        $this->assertSame('rendang', $dish->refresh()->slug);
    }

    public function test_price_and_availability_changes_are_audited_without_free_text(): void
    {
        $dish = $this->dish('soto', 38000);
        $admin = $this->adminForData();

        app(UpdateMenuItem::class)->handle($admin, $dish->refresh(), $this->payload(['slug' => 'soto', 'price' => 40000]));
        app(SetAvailability::class)->handle($admin, $dish->refresh(), false);

        $this->assertTrue(AuditEvent::query()->where('action', 'like', 'menu.%')->where('subject_id', (string) $dish->id)->exists());
        $this->assertSame(0, AuditEvent::query()->where('metadata', 'like', '%Deskripsi%')->count());
    }

    public function test_money_is_formatted_per_locale(): void
    {
        $this->assertStringContainsString('68.000', Money::format(68000, 'IDR', 'id'));
        $this->assertStringContainsString('68,000', Money::format(68000, 'IDR', 'en'));
        $this->assertStringContainsString('68,000', Money::format(68000, 'IDR', 'ja'));
    }

    public function test_the_admin_form_creates_a_draft_with_every_field(): void
    {
        $admin = $this->admin();
        $category = $this->category();

        $this->actingAs($admin)->post('/admin/menu', [
            'slug' => 'bakso',
            'category_id' => $category->id,
            'price' => 25000,
            'is_available' => '1',
            'sort_order' => 3,
            'translations' => [
                'id' => ['name' => 'Bakso', 'description' => 'Bakso sapi', 'aliases' => 'meatball, bakso urat'],
                'en' => ['name' => 'Meatball soup', 'description' => 'Beef meatballs', 'aliases' => ''],
                'ja' => ['name' => 'ミートボールスープ', 'description' => '牛肉団子', 'aliases' => ''],
            ],
            'allergens' => ['celery' => 'contains', 'soy' => ''],
            'dietary_tags' => ['halal'],
        ])->assertRedirect();

        $item = MenuItem::query()->where('slug', 'bakso')->firstOrFail();
        $this->assertSame('draft', $item->status->value);
        $this->assertSame(['celery'], $item->allergenCodes());
        $this->assertSame(['halal'], $item->dietaryTagCodes());
        $this->assertSame(['meatball', 'bakso urat'], $item->translation('id')->aliases);
    }
}
