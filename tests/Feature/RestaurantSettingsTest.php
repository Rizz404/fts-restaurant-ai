<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ClosedDate;
use App\Models\RestaurantSetting;
use App\Modules\Experience\Support\ContactLinks;
use App\Modules\Experience\Support\RestaurantJsonLd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 3: restaurant settings are admin-only, plain text, with whitelisted
 * contact links; reservation limits and opening hours drive the guest flow.
 */
class RestaurantSettingsTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->freezeClock();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function body(array $overrides = []): array
    {
        $hours = [];
        foreach (RestaurantSetting::WEEKDAYS as $day) {
            $hours[$day] = ['open' => '09:00', 'close' => '21:00'];
        }

        return array_replace_recursive([
            'brand_name' => 'Warung Uji',
            'welcome_text' => ['id' => 'Selamat datang', 'en' => 'Welcome', 'ja' => 'ようこそ'],
            'contact_links' => ['phone' => '+62 21 555 0100', 'whatsapp' => '628123456789', 'instagram' => 'warung.uji', 'maps_url' => 'https://maps.google.com/?q=eval'],
            'slot_capacity' => 12,
            'max_party_size' => 8,
            'min_lead_minutes' => 30,
            'max_advance_days' => 14,
            'opening_hours' => $hours,
            'closed_dates' => "2026-12-25 Natal\n2026-12-31",
        ], $overrides);
    }

    public function test_only_admins_can_open_or_change_the_settings(): void
    {
        foreach ([$this->editor(), $this->staff()] as $user) {
            $this->actingAs($user)->get('/admin/settings')->assertForbidden();
            $this->actingAs($user)->patch('/admin/settings', $this->body())->assertForbidden();
        }

        $this->actingAs($this->admin())->get('/admin/settings')->assertOk()->assertSee(config('restaurant.brand.name'));
    }

    public function test_admin_saves_settings_hours_and_closed_dates_with_an_audit_event(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch('/admin/settings', $this->body())->assertRedirect(route('admin.settings.edit'));

        $row = RestaurantSetting::query()->findOrFail(RestaurantSetting::SINGLETON_ID);
        $this->assertSame('Warung Uji', $row->brand_name);
        $this->assertSame(12, $row->slotCapacity());
        $this->assertSame(8, $row->maxPartySize());
        $this->assertSame([['open' => '09:00', 'close' => '21:00']], $row->openingHours()['wed']);
        $this->assertSame(['2026-12-25', '2026-12-31'], ClosedDate::query()->orderBy('date')->get()->map(fn ($d) => $d->date->format('Y-m-d'))->all());

        $audit = AuditEvent::query()->where('action', 'restaurant_settings.updated')->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertContains('closed_dates', $audit->metadata['fields']);
        $this->assertStringNotContainsString('+62 21 555 0100', json_encode($audit->metadata));
    }

    public function test_markup_urls_and_script_like_text_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch('/admin/settings', $this->body([
            'brand_name' => '<b>Warung</b>',
            'welcome_text' => ['en' => 'Visit https://evil.example', 'id' => 'javascript:alert(1)'],
        ]))->assertSessionHasErrors(['brand_name', 'welcome_text.en', 'welcome_text.id']);
    }

    public function test_contact_links_are_whitelisted_and_rebuilt_by_the_server(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch('/admin/settings', $this->body(['contact_links' => [
            'phone' => 'call me',
            'whatsapp' => '+62-812',
            'instagram' => 'not valid!',
            'maps_url' => 'https://evil.example/maps',
        ]]))->assertSessionHasErrors(['contact_links.phone', 'contact_links.whatsapp', 'contact_links.instagram', 'contact_links.maps_url']);

        $this->assertFalse(ContactLinks::isValidMapsUrl('http://maps.google.com/?q=x'));
        $this->assertFalse(ContactLinks::isValidMapsUrl('https://user:pass@maps.google.com/'));
        $this->assertTrue(ContactLinks::isValidMapsUrl('https://maps.app.goo.gl/abc'));

        $links = ContactLinks::build(['whatsapp' => '628123456789', 'instagram' => 'warung.uji', 'maps_url' => 'javascript:alert(1)']);
        $this->assertSame(['whatsapp', 'instagram'], array_column($links, 'type'));
        $this->assertSame('https://wa.me/628123456789', $links[0]['href']);
    }

    public function test_hours_must_close_after_they_open_and_come_in_pairs(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch('/admin/settings', $this->body(['opening_hours' => ['mon' => ['open' => '21:00', 'close' => '09:00']]]))
            ->assertSessionHasErrors('opening_hours.mon.close');
        $this->actingAs($admin)->patch('/admin/settings', $this->body(['opening_hours' => ['tue' => ['open' => '09:00', 'close' => '']]]))
            ->assertSessionHasErrors('opening_hours.tue.close');
    }

    public function test_malformed_closed_dates_are_rejected(): void
    {
        $this->actingAs($this->admin())->patch('/admin/settings', $this->body(['closed_dates' => '25-12-2026 Natal']))
            ->assertSessionHasErrors('closed_dates');
    }

    public function test_saved_hours_and_limits_drive_the_availability_endpoint(): void
    {
        $this->actingAs($this->admin())->patch('/admin/settings', $this->body(['max_advance_days' => 120]))->assertRedirect();
        $this->flushSession();
        auth()->logout();

        [$date] = $this->openDay();
        $slots = $this->getJson("/api/v1/availability?date={$date}&party_size=2")->assertOk()->json('data');

        $this->assertSame('09:00', $slots[0]['slot']);
        $this->assertSame(12, $slots[0]['available_seats']);
        $this->getJson("/api/v1/availability?date={$date}&party_size=9")->assertStatus(422);
        $this->getJson('/api/v1/availability?date=2026-12-25&party_size=2')->assertOk()->assertJsonPath('meta.closed', true);
    }

    public function test_json_ld_uses_only_settings_data(): void
    {
        $this->actingAs($this->admin())->patch('/admin/settings', $this->body())->assertRedirect();

        $data = app(RestaurantJsonLd::class)->build('Intro');

        $this->assertSame('Restaurant', $data['@type']);
        $this->assertSame('Warung Uji', $data['name']);
        $this->assertSame('+62 21 555 0100', $data['telephone']);
        $this->assertSame(['https://www.instagram.com/warung.uji'], $data['sameAs']);
        $this->assertCount(7, $data['openingHoursSpecification']);
        $this->assertSame('09:00', $data['openingHoursSpecification'][0]['opens']);
    }
}
