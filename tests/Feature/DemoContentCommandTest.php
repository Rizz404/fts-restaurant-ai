<?php

namespace Tests\Feature;

use App\Models\KnowledgeArticle;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Modules\Assistant\Tools\ToolRegistry;
use Database\Seeders\MenuDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\BuildsRestaurantData;
use Tests\Support\TalksToAssistant;
use Tests\TestCase;

class DemoContentCommandTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;
    use TalksToAssistant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->adminForData();
    }

    public function test_it_fills_an_empty_restaurant_with_published_demo_content(): void
    {
        $this->artisan('restaurant:seed-demo-content')->assertSuccessful();

        $this->assertSame(8, MenuItem::query()->count());
        $this->assertSame(3, MenuCategory::query()->count());
        $this->assertSame(4, KnowledgeArticle::query()->count());
        $this->assertSame(0, MenuItem::query()->where('slug', 'not like', 'demo-%')->count());

        $this->get('/menu?lang=en')->assertOk()->assertSee('[DEMO] Beef rendang');
    }

    public function test_the_ai_host_finds_demo_dishes_and_offers_a_category_filter(): void
    {
        $this->artisan('restaurant:seed-demo-content')->assertSuccessful();
        $this->toolsEnabled(true);
        $this->withoutMiddleware(ThrottleRequests::class);
        RateLimiter::clear('guest-session-create');

        $schema = collect(app(ToolRegistry::class)->definitions())->firstWhere('function.name', 'search_menu');
        $this->assertSame(['starters', 'mains', 'drinks'], $schema['function']['parameters']['properties']['category']['enum']);

        $rendang = MenuItem::query()->where('slug', 'demo-rendang-sapi')->firstOrFail();
        $this->fakeModel([$this->toolCall('search_menu', ['query' => 'rendang']), $this->finalAnswer('[DEMO] Rendang ada.', [$rendang->id])]);
        $answer = $this->say($this->startConversation('menu', 'en'), 'do you have rendang?', locale: 'en')->assertOk();

        $this->assertSame(68000, $this->cardsOf($answer, 'menu_item_card')['price']);
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->artisan('restaurant:seed-demo-content')->assertSuccessful();
        $before = MenuItem::query()->orderBy('id')->get(['id', 'slug', 'price', 'updated_at'])->toArray();

        $this->artisan('restaurant:seed-demo-content')->assertSuccessful();

        $this->assertSame($before, MenuItem::query()->orderBy('id')->get(['id', 'slug', 'price', 'updated_at'])->toArray());
        $this->assertSame(4, KnowledgeArticle::query()->count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->artisan('restaurant:seed-demo-content', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, MenuItem::query()->count());
        $this->assertSame(0, KnowledgeArticle::query()->count());
    }

    public function test_it_refuses_when_real_content_exists(): void
    {
        $this->dish('house-special', 50000);

        $this->artisan('restaurant:seed-demo-content')->assertFailed();

        $this->assertSame(['house-special'], MenuItem::query()->pluck('slug')->all());
    }

    public function test_production_needs_the_explicit_confirmation_flag(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('restaurant:seed-demo-content')->assertFailed();
        $this->assertSame(0, MenuItem::query()->count());

        $this->artisan('restaurant:seed-demo-content', ['--confirm-production' => true])->assertSuccessful();
        $this->assertSame(8, MenuItem::query()->count());
        $this->assertFalse(MenuDemoSeeder::$allowOutsideLocal, 'The production allowance is reset after the run.');
    }

    public function test_the_demo_seeders_still_refuse_production_on_their_own(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->app->make(MenuDemoSeeder::class)->setContainer($this->app)->__invoke();
    }
}
