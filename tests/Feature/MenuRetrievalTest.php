<?php

namespace Tests\Feature;

use App\Modules\Menu\Actions\ArchiveMenuItem;
use App\Modules\Menu\Actions\CreateMenuItem;
use App\Modules\Menu\Data\MenuFilters;
use App\Modules\Menu\MenuRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsRestaurantData;
use Tests\TestCase;

/**
 * Plan 4: deterministic menu retrieval (no model): multi-language queries,
 * aliases, normalization, ranking, filters, escaping, and visibility.
 */
class MenuRetrievalTest extends TestCase
{
    use BuildsRestaurantData;
    use RefreshDatabase;

    private MenuRetriever $menu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->menu = app(MenuRetriever::class);
    }

    /**
     * @return list<string>
     */
    private function slugs(string $query, string $locale = 'id', array $filters = []): array
    {
        $result = $this->menu->search($locale, MenuFilters::fromArray(['query' => $query] + $filters));

        return array_map(fn ($item) => $item->slug, $result->items);
    }

    private function fried(): void
    {
        $this->dish('nasi-goreng', 48000, [], ['halal'], [
            'names' => ['id' => 'Nasi goreng spesial', 'en' => 'Special fried rice', 'ja' => 'スペシャルチャーハン'],
            'aliases' => ['id' => ['nasi goreng kampung'], 'en' => ['fried rice', 'egg rice'], 'ja' => ['焼き飯']],
        ]);
    }

    public function test_queries_in_each_language_find_the_same_dish_and_return_text_in_the_requested_locale(): void
    {
        $this->fried();

        $this->assertSame(['nasi-goreng'], $this->slugs('nasi goreng', 'id'));
        $this->assertSame(['nasi-goreng'], $this->slugs('special fried rice', 'en'));
        $this->assertSame(['nasi-goreng'], $this->slugs('チャーハン', 'ja'));

        $evidence = $this->menu->search('en', MenuFilters::fromArray(['query' => 'nasi goreng']))->items[0];
        $this->assertSame('Special fried rice', $evidence->name, 'A match in Indonesian is shown in English.');
    }

    public function test_aliases_and_cross_language_words_match(): void
    {
        $this->fried();

        $this->assertSame(['nasi-goreng'], $this->slugs('fried rice', 'id'));
        $this->assertSame(['nasi-goreng'], $this->slugs('egg rice', 'ja'));
        $this->assertSame(['nasi-goreng'], $this->slugs('焼き飯', 'en'));
    }

    public function test_text_is_normalized_before_matching(): void
    {
        $this->fried();

        // Full-width Latin (NFKC) and mixed case.
        $this->assertSame(['nasi-goreng'], $this->slugs('ＮＡＳＩ　ＧＯＲＥＮＧ'));
        $this->assertSame(['nasi-goreng'], $this->slugs('NASI Goreng'));
    }

    public function test_cjk_text_matches_by_bigrams(): void
    {
        $this->dish('chahan', 40000, [], [], ['names' => ['id' => 'Chahan', 'en' => 'Chahan', 'ja' => '海老チャーハン']]);

        $this->assertSame(['chahan'], $this->slugs('チャーハン', 'ja'));
        $this->assertSame(['chahan'], $this->slugs('海老', 'ja'));
        $this->assertSame([], $this->slugs('寿司', 'ja'));
    }

    public function test_a_name_match_outranks_a_description_match_and_ties_follow_sort_order_then_id(): void
    {
        $this->dish('menu-b', 30000, options: ['names' => ['id' => 'Ayam', 'en' => 'Chicken', 'ja' => '鶏'], 'descriptions' => ['id' => 'Dengan sate.', 'en' => 'With satay.', 'ja' => 'サテ付き。'], 'sort' => 1]);
        $this->dish('menu-a', 30000, options: ['names' => ['id' => 'Sate kambing', 'en' => 'Goat satay', 'ja' => 'ヤギ串'], 'sort' => 5]);
        $this->dish('menu-c', 30000, options: ['names' => ['id' => 'Sate ayam', 'en' => 'Chicken satay', 'ja' => '鶏串'], 'sort' => 2]);
        $this->dish('menu-d', 30000, options: ['names' => ['id' => 'Sate udang', 'en' => 'Prawn satay', 'ja' => 'エビ串'], 'sort' => 2]);

        // Two name matches tie on score: sort order, then id. The description-only match is last.
        $this->assertSame(['menu-c', 'menu-d', 'menu-a', 'menu-b'], $this->slugs('sate'));
    }

    public function test_available_dishes_come_before_sold_out_ones_whatever_the_score(): void
    {
        $this->dish('es-teler', 28000, options: ['names' => ['id' => 'Es teler', 'en' => 'Es teler', 'ja' => 'エステレル'], 'available' => false, 'sort' => 1]);
        $this->dish('es-teh', 12000, options: ['names' => ['id' => 'Minuman dingin', 'en' => 'Cold drink', 'ja' => '冷たい飲み物'], 'descriptions' => ['id' => 'Seperti es teler tapi teh.', 'en' => 'Like es teler but tea.', 'ja' => 'エステレル風のお茶。'], 'sort' => 9]);

        $this->assertSame(['es-teh', 'es-teler'], $this->slugs('es teler'));
    }

    public function test_category_price_and_diet_filters(): void
    {
        $this->dish('gulai', 36000, [], ['vegan'], ['category' => 'mains']);
        $this->dish('cendol', 24000, [], ['vegan'], ['category' => 'desserts']);
        $this->dish('rendang', 68000, [], ['halal'], ['category' => 'mains']);

        $filter = fn (array $f) => array_map(fn ($i) => $i->slug, $this->menu->search('id', MenuFilters::fromArray($f))->items);

        $this->assertEqualsCanonicalizing(['gulai', 'rendang'], $filter(['category' => 'mains']));
        $this->assertEqualsCanonicalizing(['cendol'], $filter(['max_price' => 30000]));
        $this->assertEqualsCanonicalizing(['gulai', 'cendol'], $filter(['dietary_tags' => ['vegan']]));
        $this->assertSame([], $filter(['category' => 'mains', 'dietary_tags' => ['vegan'], 'max_price' => 30000]));
        $this->assertCount(3, $filter([]));
    }

    public function test_unknown_filter_codes_are_dropped_not_trusted(): void
    {
        $filters = MenuFilters::fromArray(['dietary_tags' => ['vegan', 'keto', 5], 'exclude_allergens' => ['peanuts', 'unicorn'], 'max_price' => '-5']);

        $this->assertSame(['vegan'], $filters->dietaryTags);
        $this->assertSame(['peanuts'], $filters->excludeAllergens);
        $this->assertNull($filters->maxPrice);
    }

    public function test_like_wildcards_are_escaped(): void
    {
        $this->dish('soup', 20000, options: ['names' => ['id' => 'Soup', 'en' => 'Soup', 'ja' => 'スープ']]);

        $this->assertSame(['soup'], $this->slugs('soup'));
        $this->assertSame([], $this->slugs('%'), 'A percent sign alone matches nothing.');
        $this->assertSame([], $this->slugs('_'));

        // Punctuation splits words; it never acts as a LIKE wildcard, so the exact-phrase bonus is not awarded.
        $exact = $this->menu->search('id', MenuFilters::fromArray(['query' => 'soup']))->items[0]->score;
        $wildcard = $this->menu->search('id', MenuFilters::fromArray(['query' => 's_up']))->items[0]->score;
        $this->assertGreaterThanOrEqual(30, $exact);
        $this->assertLessThan(30, $wildcard);
    }

    public function test_drafts_and_archived_dishes_are_never_returned(): void
    {
        $admin = $this->adminForData();
        $this->dish('visible', 30000, options: ['names' => ['id' => 'Soto terlihat', 'en' => 'Visible soto', 'ja' => 'ソト']]);
        $archived = $this->dish('archived', 30000, options: ['names' => ['id' => 'Soto arsip', 'en' => 'Archived soto', 'ja' => 'ソト2']]);
        app(ArchiveMenuItem::class)->handle($admin, $archived);
        app(CreateMenuItem::class)->handle($admin, [
            'slug' => 'draft', 'category_id' => $this->category()->id, 'price' => 30000, 'is_available' => true, 'is_chef_pick' => false, 'spice_level' => null, 'sort_order' => 1,
            'translations' => ['id' => ['name' => 'Soto draf', 'description' => 'x', 'aliases' => []], 'en' => ['name' => 'Draft soto', 'description' => 'x', 'aliases' => []], 'ja' => ['name' => 'ソト3', 'description' => 'x', 'aliases' => []]],
            'allergens' => [], 'dietary_tags' => [],
        ]);

        $this->assertSame(['visible'], $this->slugs('soto'));
        $this->assertNull($this->menu->find('draft'));
        $this->assertNull($this->menu->find('archived'));
        $this->assertNotNull($this->menu->find('visible'));
    }

    public function test_results_are_capped_at_eight(): void
    {
        foreach (range(1, 11) as $i) {
            $this->dish("kuah-{$i}", 20000 + $i, options: ['names' => ['id' => "Kuah {$i}", 'en' => "Broth {$i}", 'ja' => "汁{$i}"], 'sort' => $i]);
        }

        $this->assertCount(8, $this->slugs('kuah'));
        $this->assertCount(3, $this->menu->search('id', MenuFilters::fromArray(['query' => 'kuah']), 3)->items);
        $this->assertCount(11, $this->menu->catalog('id', MenuFilters::fromArray([]))->items);
    }

    public function test_chef_picks_are_only_available_picked_dishes(): void
    {
        $this->dish('pick-a', 50000, options: ['chef' => true, 'sort' => 2]);
        $this->dish('pick-b', 50000, options: ['chef' => true, 'sort' => 1]);
        $this->dish('pick-sold', 50000, options: ['chef' => true, 'available' => false]);
        $this->dish('plain', 50000);

        $this->assertSame(['pick-b', 'pick-a'], array_map(fn ($item) => $item->slug, $this->menu->chefPicks('id')));
    }

    public function test_a_query_with_no_searchable_text_matches_nothing_but_a_blank_query_browses(): void
    {
        $this->dish('soto', 30000);

        $this->assertSame([], $this->slugs('!!!'));
        $this->assertSame(['soto'], $this->slugs('   '), 'A blank query is no query: the menu is browsed.');
    }
}
