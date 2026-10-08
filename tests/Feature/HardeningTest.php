<?php

namespace Tests\Feature;

use App\Models\RestaurantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 8, public-site hardening: security headers, no remote sources, client
 * IP behind a trusted proxy, crawler rules, and SEO metadata.
 */
class HardeningTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->freezeClock();
        RateLimiter::clear('guest-session-create');
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    public function test_pages_and_the_api_carry_the_security_headers(): void
    {
        foreach (['/', '/menu', '/login', '/api/v1/menu', '/up'] as $path) {
            $response = $this->get($path);
            $response->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('X-Frame-Options', 'DENY');
            $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
            $this->assertStringContainsString('microphone=()', $response->headers->get('Permissions-Policy'));
            $this->assertStringContainsString('geolocation=()', $response->headers->get('Permissions-Policy'));
        }
    }

    public function test_the_content_security_policy_is_strict_and_records_the_alpine_decision(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("connect-src 'self'", $csp);
        $this->assertStringContainsString("img-src 'self' data:", $csp);

        $script = collect(explode('; ', $csp))->first(fn ($directive) => str_starts_with($directive, 'script-src'));
        $this->assertSame("script-src 'self' 'unsafe-eval'", $script, 'Alpine 3 needs unsafe-eval; unsafe-inline for scripts is never allowed.');
        $style = collect(explode('; ', $csp))->first(fn ($directive) => str_starts_with($directive, 'style-src '));
        $this->assertSame("style-src 'self'", $style);
        $this->assertStringNotContainsString('http', $csp, 'No remote origin is allowed anywhere.');
        $this->assertStringNotContainsString('*', $csp);
    }

    public function test_the_policy_can_be_switched_off_by_configuration_only(): void
    {
        config(['restaurant.security.csp' => false]);

        $this->get('/')->assertHeaderMissing('Content-Security-Policy')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_hsts_is_only_sent_on_https(): void
    {
        $this->get('http://localhost/menu')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/menu')->assertHeader('Strict-Transport-Security');

        config(['restaurant.security.hsts' => false]);
        $this->get('https://localhost/menu')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_no_template_or_script_uses_unsafe_html_sinks(): void
    {
        $offenders = [];
        foreach ([resource_path('views'), resource_path('js')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                $content = File::get($file->getPathname());
                foreach (['x-html', 'innerHTML', 'insertAdjacentHTML', 'outerHTML', 'document.write', 'eval('] as $sink) {
                    if (str_contains($content, $sink)) {
                        $offenders[] = $file->getRelativePathname().' uses '.$sink;
                    }
                }
            }
        }

        $this->assertSame([], $offenders);
    }

    public function test_pages_load_no_remote_scripts_styles_images_or_fonts_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->dish('rendang', 68000);

        foreach (['/', '/menu', '/menu/rendang', '/info', '/reservation', '/order', '/bookings', '/login'] as $path) {
            $html = $this->get($path)->getContent();
            preg_match_all('/<(?:script|img|link|source|iframe)\b[^>]*\b(?:src|href)="(https?:)?\/\/([^"\/]+)/i', $html, $matches);
            $remote = array_values(array_filter($matches[2], fn ($host) => preg_replace('/[:?].*/', '', $host) !== parse_url(config('app.url'), PHP_URL_HOST)));
            $this->assertSame([], $remote, "{$path} loads from ".($remote[0] ?? ''));
            $this->assertStringNotContainsString('@import', $html);
            $this->assertStringNotContainsString('fonts.googleapis', $html);
        }
    }

    public function test_the_client_ip_comes_from_a_trusted_proxy_not_from_the_header_alone(): void
    {
        // Without a trusted proxy a forged header is ignored: everyone shares one bucket.
        $untrusted = fn (string $ip) => $this->withHeader('X-Forwarded-For', $ip)->postJson('/api/v1/requests/lookup', ['reference' => 'RST-20261007-ABCDEFGH', 'contact' => 'x@example.test'])->getStatusCode();
        $codes = [];
        for ($i = 0; $i < 12; $i++) {
            $codes[] = $untrusted('203.0.113.'.(10 + $i));
        }
        $this->assertContains(429, $codes, 'Forged forwarding headers must not give each request its own limit.');

        // Behind a trusted proxy each real client has its own bucket.
        Cache::flush();
        TrustProxies::at('*');
        $a = [];
        for ($i = 0; $i < 11; $i++) {
            $a[] = $this->withHeader('X-Forwarded-For', '198.51.100.1')->postJson('/api/v1/requests/lookup', ['reference' => 'RST-20261007-ABCDEFGH', 'contact' => 'x@example.test'])->getStatusCode();
        }
        $this->assertSame(429, end($a));
        $this->withHeader('X-Forwarded-For', '198.51.100.2')->postJson('/api/v1/requests/lookup', ['reference' => 'RST-20261007-ABCDEFGH', 'contact' => 'x@example.test'])
            ->assertStatus(404);
    }

    public function test_robots_txt_allows_public_pages_and_blocks_private_ones(): void
    {
        $robots = File::get(public_path('robots.txt'));

        $this->assertStringContainsString('Allow: /', $robots);
        foreach (['/api/', '/admin', '/bookings', '/login'] as $path) {
            $this->assertStringContainsString("Disallow: {$path}", $robots);
        }
        $this->assertStringNotContainsString('Disallow: /menu', $robots);
        $this->assertStringNotContainsString('Disallow: /info', $robots);
    }

    public function test_private_pages_are_noindex_and_public_pages_are_indexable(): void
    {
        foreach (['/login', '/bookings'] as $path) {
            $this->get($path)->assertSee('<meta name="robots" content="noindex, nofollow">', false)->assertDontSee('rel="canonical"', false);
        }
        $this->actingAs($this->admin())->get('/admin')->assertSee('noindex', false);

        foreach (['/', '/menu', '/info', '/reservation'] as $path) {
            $this->get($path)->assertDontSee('noindex', false)->assertSee('rel="canonical"', false);
        }
    }

    public function test_public_pages_have_canonical_hreflang_description_and_open_graph(): void
    {
        $this->dish('rendang', 68000, options: ['descriptions' => ['id' => 'Daging sapi empuk.', 'en' => 'Tender beef.', 'ja' => '柔らかい牛肉。']]);

        $html = $this->get('/menu/rendang?lang=en&utm=x')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.url('/menu/rendang').'">', $html, 'The canonical URL has no query string.');
        foreach (['id', 'en', 'ja'] as $locale) {
            $this->assertStringContainsString('hreflang="'.$locale.'" href="'.url('/menu/rendang').'?lang='.$locale.'"', $html);
        }
        $this->assertStringContainsString('hreflang="x-default"', $html);
        $this->assertStringContainsString('<meta name="description" content="Tender beef.">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Dish rendang">', $html);
        $this->assertStringContainsString('<meta property="og:locale" content="en_US">', $html);
        $this->assertStringContainsString('og-image.png', $html);
        $this->assertFileExists(public_path('assets/restaurant/og-image.png'));
    }

    public function test_json_ld_is_valid_on_welcome_and_info_and_cannot_break_out_of_the_script(): void
    {
        RestaurantSetting::query()->updateOrCreate(['id' => RestaurantSetting::SINGLETON_ID], [
            'brand_name' => '</script><script>alert(1)</script>',
            'welcome_text' => ['id' => 'a', 'en' => 'a', 'ja' => 'a'],
        ]);

        foreach (['/', '/info'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match), $path);
            $data = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('Restaurant', $data['@type']);
            $this->assertSame('</script><script>alert(1)</script>', $data['name']);
            $this->assertStringNotContainsString('<script>alert(1)', $match[1]);
        }

        $this->get('/menu')->assertDontSee('application/ld+json', false);
    }

    public function test_dev_previews_do_not_exist_in_production(): void
    {
        $this->app['env'] = 'production';

        foreach (['/dev/ui', '/dev/admin', '/dev/user', '/dev/guest'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }
}
