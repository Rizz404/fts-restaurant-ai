<?php

namespace Tests\Feature;

use App\Models\RestaurantSetting;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Experience\Support\SceneImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 3: the seven public slides, locales, scene context, branding, and
 * the settings the guest deck reads.
 */
class ExperienceSceneTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    private const PATHS = [
        'welcome' => '/',
        'menu' => '/menu',
        'picks' => '/picks',
        'reservation' => '/reservation',
        'order' => '/order',
        'bookings' => '/bookings',
        'info' => '/info',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->freezeClock();
    }

    /**
     * @return array<string, mixed>
     */
    private function sceneContext(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" id="scene-context">(.*?)</script>#s', $html, $match), 'scene-context script missing');

        return json_decode(html_entity_decode($match[1]), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_all_seven_slides_render_without_login(): void
    {
        $this->assertSame(array_keys(self::PATHS), SceneCode::values());

        foreach (self::PATHS as $scene => $path) {
            $response = $this->get($path)->assertOk();
            $this->assertSame($scene, $this->sceneContext($response->getContent())['scene'], $path);
        }
    }

    public function test_each_slide_has_its_label_in_three_languages(): void
    {
        $expected = ['id' => 'Menu', 'en' => 'Menu', 'ja' => 'メニュー'];

        foreach ($expected as $locale => $label) {
            $this->get("/menu?lang={$locale}")
                ->assertOk()
                ->assertSee('<html lang="'.$locale.'">', false)
                ->assertSee($label);
        }

        $this->get('/reservation?lang=en')->assertSee('Reservation');
        $this->get('/reservation?lang=id')->assertSee('Reservasi');
        $this->get('/reservation?lang=ja')->assertSee('予約');
    }

    public function test_an_invalid_locale_is_ignored_on_pages(): void
    {
        $this->get('/?lang=fr')->assertOk()->assertSee('<html lang="id">', false);
    }

    public function test_welcome_shows_the_brand_preferences_and_every_room(): void
    {
        $response = $this->get('/')->assertOk()
            ->assertSee(config('restaurant.brand.name'))
            ->assertSee('aria-pressed', false);

        foreach (['/menu', '/picks', '/reservation', '/order', '/bookings', '/info'] as $path) {
            $response->assertSee('href="'.url($path).'"', false);
        }
    }

    public function test_table_label_is_shown_and_escaped(): void
    {
        $this->get('/?table=A3')->assertOk()->assertSee('A3');
        $this->get('/menu?table=%3Cb%3Ex%3C%2Fb%3E')->assertOk()->assertDontSee('<b>x</b>', false);
    }

    public function test_scene_context_carries_the_dish_on_screen(): void
    {
        $dish = $this->dish('rendang-sapi', 68000);

        $response = $this->get('/menu/rendang-sapi')->assertOk()->assertSee('Hidangan rendang-sapi');
        $context = $this->sceneContext($response->getContent());

        $this->assertSame('menu', $context['scene']);
        $this->assertSame($dish->id, $context['menu_item_id']);
        $this->assertNull($context['request_public_id']);
    }

    public function test_the_staff_area_redirects_guests_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/admin/settings')->assertRedirect(route('login'));
    }

    public function test_scene_images_use_the_local_assets_and_production_never_uses_placeholders(): void
    {
        config(['restaurant.scenes.image_source' => 'placeholder', 'restaurant.scenes.placeholder_base_url' => 'https://picsum.photos']);

        $images = app(SceneImages::class);
        $this->assertSame(SceneImages::SOURCE_PLACEHOLDER, $images->source());
        // Fixed seed: the same URL on every call, with nothing from the user or session.
        $this->assertSame($images->backdrop(SceneCode::Menu), $images->backdrop(SceneCode::Menu));
        $this->assertStringContainsString('/seed/restaurant-menu/', $images->backdrop(SceneCode::Menu));

        $this->app['env'] = 'production';
        $this->assertSame(SceneImages::SOURCE_LOCAL, app(SceneImages::class)->source());
        $this->assertStringContainsString('/assets/restaurant/menu.svg', app(SceneImages::class)->backdrop(SceneCode::Menu));
    }

    public function test_welcome_text_comes_from_the_settings_for_each_locale(): void
    {
        RestaurantSetting::query()->updateOrCreate(['id' => RestaurantSetting::SINGLETON_ID], [
            'brand_name' => 'Warung Uji',
            'welcome_text' => ['id' => 'Selamat datang di Warung Uji', 'en' => 'Welcome to Warung Uji', 'ja' => 'ようこそ Warung Uji'],
        ]);

        $this->get('/?lang=en')->assertSee('Welcome to Warung Uji');
        $this->get('/?lang=ja')->assertSee('ようこそ Warung Uji');
        $this->get('/?lang=id')->assertSee('Selamat datang di Warung Uji');
    }
}
