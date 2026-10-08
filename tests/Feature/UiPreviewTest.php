<?php

namespace Tests\Feature;

use App\Http\Controllers\Dev\PreviewController;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UiPreviewTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function previewPaths(): array
    {
        return [
            'gallery' => ['/dev/ui'],
            'admin' => ['/dev/admin'],
            'user' => ['/dev/user'],
            'guest' => ['/dev/guest'],
        ];
    }

    #[DataProvider('previewPaths')]
    public function test_previews_render_in_testing(string $path): void
    {
        $this->get($path)
            ->assertOk()
            ->assertSee(__('ui.dev.banner'))
            ->assertSee('id="main-content"', false);
    }

    #[DataProvider('previewPaths')]
    public function test_previews_return_404_in_production(string $path): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->get($path)->assertNotFound();
    }

    #[DataProvider('previewPaths')]
    public function test_previews_load_the_bundle_once(string $path): void
    {
        $html = $this->get($path)->getContent();

        $this->assertSame(1, substr_count($html, 'name="csrf-token"'));
        $this->assertSame(1, substr_count($html, 'aria-live="polite"'));
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
        $this->assertStringNotContainsString('unpkg.com', $html);
    }

    public function test_preview_labels_follow_locale(): void
    {
        $this->get('/dev/admin?lang=id')->assertSee('Ringkasan admin')->assertSee('Akun');
        $this->get('/dev/admin?lang=en')->assertSee('Admin overview')->assertSee('Accounts');
        $this->get('/dev/admin?lang=ja')->assertSee('管理の概要')->assertSee('アカウント');
    }

    public function test_synthetic_content_is_escaped(): void
    {
        foreach (['/dev/ui', '/dev/admin', '/dev/user'] as $path) {
            $this->get($path)
                ->assertSee(e(PreviewController::UNSAFE_SAMPLE), false)
                ->assertDontSee(PreviewController::UNSAFE_SAMPLE, false);
        }
    }

    public function test_menu_items_without_routes_are_disabled(): void
    {
        $this->get('/dev/admin?lang=en')
            ->assertSee('aria-current="page"', false)
            ->assertSee('aria-disabled="true"', false)
            ->assertSee('Soon');
    }

    public function test_gallery_shows_validation_errors_linked_to_controls(): void
    {
        $this->get('/dev/ui?lang=en')
            ->assertSee('id="preview-error-email-error"', false)
            ->assertSee('aria-describedby="preview-error-email-error"', false)
            ->assertSee('aria-describedby="preview-error-subject-help preview-error-subject-error"', false)
            ->assertSee('Enter a valid email address.');
    }

    public function test_gallery_pagination_uses_restaurant_view(): void
    {
        $this->get('/dev/ui?page=2')
            ->assertOk()
            ->assertSee('aria-current="page"', false)
            ->assertSee('rel="prev"', false)
            ->assertSee('rel="next"', false);
    }
}
