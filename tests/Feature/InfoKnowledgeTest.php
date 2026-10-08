<?php

namespace Tests\Feature;

use App\Models\KnowledgeArticle;
use App\Modules\Knowledge\KnowledgeRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 4: restaurant info articles. Editors and admins write drafts, only
 * admins publish and archive; guests only ever see the published revision.
 */
class InfoKnowledgeTest extends TestCase
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
    private function body(string $slug = 'jam-buka', array $overrides = []): array
    {
        return array_replace_recursive([
            'slug' => $slug,
            'category' => 'hours',
            'effective_from' => '',
            'effective_until' => '',
            'translations' => [
                'id' => ['title' => 'Jam buka', 'body' => 'Buka 10.00 sampai 22.00.', 'tags' => 'jam', 'aliases' => 'buka'],
                'en' => ['title' => 'Opening hours', 'body' => 'Open 10:00 to 22:00.', 'tags' => 'hours', 'aliases' => 'open'],
                'ja' => ['title' => '営業時間', 'body' => '10:00から22:00まで。', 'tags' => '営業', 'aliases' => '開店'],
            ],
        ], $overrides);
    }

    public function test_editor_writes_a_draft_but_cannot_publish_or_archive(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->post('/admin/info', $this->body())->assertRedirect();
        $article = KnowledgeArticle::query()->where('slug', 'jam-buka')->firstOrFail();

        $this->get('/info/jam-buka')->assertNotFound();
        $this->actingAs($editor)->post("/admin/info/{$article->id}/publication")->assertForbidden();
        $this->actingAs($editor)->delete("/admin/info/{$article->id}/publication")->assertForbidden();
        $this->get('/info/jam-buka')->assertNotFound();
    }

    public function test_admin_publishes_and_the_article_is_public_in_every_language(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/info', $this->body())->assertRedirect();
        $article = KnowledgeArticle::query()->where('slug', 'jam-buka')->firstOrFail();

        $this->actingAs($admin)->post("/admin/info/{$article->id}/publication")->assertRedirect();

        $this->get('/info/jam-buka?lang=id')->assertOk()->assertSee('Buka 10.00 sampai 22.00.');
        $this->get('/info/jam-buka?lang=en')->assertOk()->assertSee('Open 10:00 to 22:00.');
        $this->get('/info/jam-buka?lang=ja')->assertOk()->assertSee('10:00から22:00まで。');
        $this->getJson('/api/v1/info?q=hours&locale=en')->assertOk()->assertJsonPath('data.0.slug', 'jam-buka');
    }

    public function test_a_new_draft_does_not_change_what_guests_see_until_it_is_published(): void
    {
        $article = $this->infoArticle('lokasi', 'location', [
            'id' => ['title' => 'Lokasi', 'body' => 'Alamat lama.'],
            'en' => ['title' => 'Location', 'body' => 'Old address.'],
            'ja' => ['title' => '所在地', 'body' => '旧住所。'],
        ]);
        $admin = $this->adminForData();

        $this->actingAs($admin)->post("/admin/info/{$article->id}/revisions")->assertRedirect();
        $this->actingAs($admin)->patch("/admin/info/{$article->id}/draft", $this->body('lokasi', [
            'category' => 'location',
            'translations' => ['en' => ['title' => 'Location', 'body' => 'New address.']],
        ]))->assertRedirect();

        $this->get('/info/lokasi?lang=en')->assertSee('Old address.')->assertDontSee('New address.');

        $this->actingAs($admin)->post("/admin/info/{$article->id}/publication")->assertRedirect();

        $this->get('/info/lokasi?lang=en')->assertSee('New address.')->assertDontSee('Old address.');
        $this->assertSame(1, $article->revisions()->where('status', 'published')->count());
        $this->assertSame(2, $article->revisions()->max('version'));
    }

    public function test_archiving_removes_the_article_from_search_and_pages(): void
    {
        $article = $this->infoArticle('kebijakan', 'policy');

        $this->actingAs($this->adminForData())->delete("/admin/info/{$article->id}/publication")->assertRedirect();

        $this->get('/info/kebijakan')->assertNotFound();
        $this->getJson('/api/v1/info?q=kebijakan')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_effective_window_controls_visibility_and_future_publication_is_refused(): void
    {
        $admin = $this->adminForData();

        // No future scheduling in v1: a draft that starts later cannot be published.
        $this->actingAs($admin)->post('/admin/info', $this->body('masa-depan', ['effective_from' => '2026-12-01']))->assertRedirect();
        $future = KnowledgeArticle::query()->where('slug', 'masa-depan')->firstOrFail();
        $this->actingAs($admin)->post("/admin/info/{$future->id}/publication")->assertSessionHasErrors('effective_from');
        $this->get('/info/masa-depan')->assertNotFound();

        // An end date that has already passed is refused as well.
        $this->actingAs($admin)->post('/admin/info', $this->body('sudah-lewat', ['effective_until' => '2026-10-01']))->assertRedirect();
        $past = KnowledgeArticle::query()->where('slug', 'sudah-lewat')->firstOrFail();
        $this->actingAs($admin)->post("/admin/info/{$past->id}/publication")->assertSessionHasErrors('effective_until');

        // A window that ends later is visible until its end.
        $this->actingAs($admin)->post('/admin/info', $this->body('promo', ['effective_until' => '2026-10-17']))->assertRedirect();
        $promo = KnowledgeArticle::query()->where('slug', 'promo')->firstOrFail();
        $this->actingAs($admin)->post("/admin/info/{$promo->id}/publication")->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/info/promo')->assertOk();
        $this->freezeClock('2026-10-16 12:00:00');
        $this->get('/info/promo')->assertOk();
        $this->freezeClock('2026-10-18 12:00:00');
        $this->get('/info/promo')->assertNotFound();
        $this->getJson('/api/v1/info?q=hours')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_slug_and_translations_are_validated(): void
    {
        $admin = $this->admin();

        // A free-form slug is normalized, not rejected.
        $this->actingAs($admin)->post('/admin/info', $this->body('Slug Dengan Spasi!!'))->assertRedirect();
        $this->assertDatabaseHas('knowledge_articles', ['slug' => 'slug-dengan-spasi']);

        $this->actingAs($admin)->post('/admin/info', $this->body('jam-buka', ['category' => 'gossip']))->assertSessionHasErrors('category');

        $first = $this->body('sama');
        $this->actingAs($admin)->post('/admin/info', $first)->assertRedirect();
        $this->actingAs($admin)->post('/admin/info', $first)->assertSessionHasErrors('slug');
    }

    public function test_html_in_articles_is_shown_as_text(): void
    {
        $this->infoArticle('xss', 'faq', [
            'id' => ['title' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(2)> teks'],
            'en' => ['title' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(2)> text'],
            'ja' => ['title' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(2)> テキスト'],
        ]);

        $html = $this->get('/info/xss')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_retrieval_ranks_title_over_body_and_filters_by_category(): void
    {
        $this->infoArticle('a-title', 'hours', ['id' => ['title' => 'Parkir motor', 'body' => 'Tersedia.'], 'en' => ['title' => 'Motorbike parking', 'body' => 'Available.'], 'ja' => ['title' => 'バイク駐車場', 'body' => 'あります。']]);
        $this->infoArticle('b-body', 'location', ['id' => ['title' => 'Lokasi', 'body' => 'Ada parkir motor di depan.'], 'en' => ['title' => 'Location', 'body' => 'Motorbike parking in front.'], 'ja' => ['title' => '所在地', 'body' => '前にバイク駐車場。']]);

        $search = fn (?string $category = null) => array_map(fn ($e) => $e->slug, app(KnowledgeRetriever::class)->search('id', 'parkir motor', $category));

        $this->assertSame(['a-title', 'b-body'], $search());
        $this->assertSame(['b-body'], $search('location'));
        $this->assertSame([], app(KnowledgeRetriever::class)->search('id', '   '));
    }

    public function test_only_one_revision_can_be_published_and_one_draft_exists_at_a_time(): void
    {
        $article = $this->infoArticle('sekali', 'faq');
        $admin = $this->adminForData();

        $this->actingAs($admin)->post("/admin/info/{$article->id}/revisions")->assertRedirect();
        $this->actingAs($admin)->post("/admin/info/{$article->id}/revisions");

        $this->assertSame(1, $article->revisions()->where('status', 'draft')->count());
        $this->assertSame(1, $article->revisions()->where('status', 'published')->count());
    }

    public function test_info_categories_have_labels_in_three_languages(): void
    {
        foreach (['general', 'hours', 'location', 'policy', 'faq'] as $category) {
            foreach (['id', 'en', 'ja'] as $locale) {
                $this->assertTrue(trans()->hasForLocale("info.categories.{$category}", $locale), "{$category}/{$locale}");
            }
        }
    }
}
