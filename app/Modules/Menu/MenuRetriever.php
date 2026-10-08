<?php

namespace App\Modules\Menu;

use App\Models\MenuItem;
use App\Modules\Knowledge\Support\TextNormalizer;
use App\Modules\Menu\Data\MenuEvidence;
use App\Modules\Menu\Data\MenuFilters;
use App\Modules\Menu\Data\MenuSearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic retrieval over published dishes (PLAN_04).
 *
 * Only published dishes are considered. With a query, each dish scores its
 * best translation (all locales are searched so cross-language aliases
 * match, text is returned in the requested locale): name phrase +30,
 * keywords/aliases phrase +20, description phrase +10, and per distinct
 * matching token +3 name, +2 keywords, +1 description (NFKC, CJK bigrams,
 * at most 12 tokens, LIKE wildcards escaped, values bound). Available dishes
 * come first, then score, then sort order and id.
 *
 * Safety rule: when dietary tags or allergens to exclude are requested, only
 * dishes with verified allergen data can match, a dish containing or possibly
 * containing an excluded allergen never matches, and the number of unverified
 * dishes withheld is reported.
 */
final class MenuRetriever
{
    public const MAX_RESULTS = 8;

    public const CATALOG_LIMIT = 200;

    public function search(string $locale, MenuFilters $filters, int $limit = self::MAX_RESULTS): MenuSearchResult
    {
        $limit = max(1, min(self::MAX_RESULTS, $limit));

        return $this->run($locale, $filters, $limit, catalog: false);
    }

    /**
     * The browsable menu for pages: all published dishes matching the
     * filters, grouped order (category, then dish), capped for safety.
     */
    public function catalog(string $locale, MenuFilters $filters): MenuSearchResult
    {
        return $this->run($locale, $filters, self::CATALOG_LIMIT, catalog: true);
    }

    /**
     * Single published dish by ID or slug (any availability).
     */
    public function find(int|string $item): ?MenuItem
    {
        return MenuItem::query()
            ->published()
            ->where(is_int($item) ? 'menu_items.id' : 'menu_items.slug', $item)
            ->with(['translations', 'category.translations', 'allergens', 'dietaryTags'])
            ->first();
    }

    public function findEvidence(int|string $item, string $locale): ?MenuEvidence
    {
        $found = $this->find($item);

        return $found === null ? null : MenuEvidence::fromItem($found, $locale);
    }

    /**
     * Published dishes chef-picked and currently available.
     *
     * @return list<MenuEvidence>
     */
    public function chefPicks(string $locale, int $limit = 24): array
    {
        return MenuItem::query()
            ->published()
            ->where('menu_items.is_chef_pick', true)
            ->where('menu_items.is_available', true)
            ->orderBy('menu_items.sort_order')
            ->orderBy('menu_items.id')
            ->limit($limit)
            ->with(['translations', 'category.translations', 'allergens', 'dietaryTags'])
            ->get()
            ->map(fn (MenuItem $item) => MenuEvidence::fromItem($item, $locale))
            ->all();
    }

    private function run(string $locale, MenuFilters $filters, int $limit, bool $catalog): MenuSearchResult
    {
        $phrase = $filters->normalizedQuery();
        $tokens = TextNormalizer::tokens($phrase);

        // A query that normalizes to nothing searchable matches nothing.
        if ($filters->query !== null && ($phrase === '' || $tokens === [])) {
            return new MenuSearchResult([]);
        }

        $query = $this->baseQuery($filters, $phrase, $tokens);
        $this->applySafetyFilters($query, $filters);

        if ($catalog) {
            $query->join('menu_categories as c', 'c.id', '=', 'menu_items.category_id')
                ->orderBy('c.sort_order')
                ->orderBy('c.id');
        }

        $query->orderByDesc('menu_items.is_available');
        if ($phrase !== '') {
            $query->orderByDesc('s.score');
        }
        $query->orderBy('menu_items.sort_order')->orderBy('menu_items.id');

        $items = $query
            ->with(['translations', 'category.translations', 'allergens', 'dietaryTags'])
            ->limit($limit)
            ->get();

        return new MenuSearchResult(
            $items->map(fn (MenuItem $item) => MenuEvidence::fromItem($item, $locale, (int) ($item->score ?? 0)))->all(),
            $filters->hasSafetyFilters() ? $this->unverifiedCount($filters, $phrase, $tokens) : 0,
        );
    }

    /**
     * Published dishes matching category, price, and text (no safety filters).
     *
     * @param  list<string>  $tokens
     * @return Builder<MenuItem>
     */
    private function baseQuery(MenuFilters $filters, string $phrase, array $tokens): Builder
    {
        $query = MenuItem::query()
            ->select('menu_items.*')
            ->published()
            ->when($filters->category !== null, fn (Builder $query) => $query->whereIn(
                'menu_items.category_id',
                DB::table('menu_categories')->select('id')->where('code', $filters->category),
            ))
            ->when($filters->maxPrice !== null, fn (Builder $query) => $query->where('menu_items.price', '<=', $filters->maxPrice));

        if ($phrase !== '') {
            [$scoreSql, $bindings] = $this->scoreExpression($phrase, $tokens);

            $scores = DB::table('menu_item_translations as t')
                ->groupBy('t.item_id')
                ->selectRaw("t.item_id, MAX({$scoreSql}) as score", $bindings);

            $query->joinSub($scores, 's', 's.item_id', '=', 'menu_items.id')
                ->where('s.score', '>', 0)
                ->addSelect('s.score');
        }

        return $query;
    }

    /**
     * @param  Builder<MenuItem>  $query
     */
    private function applySafetyFilters(Builder $query, MenuFilters $filters): void
    {
        if (! $filters->hasSafetyFilters()) {
            return;
        }

        // Without verified allergen data the dish cannot be judged either way.
        $query->whereNotNull('menu_items.allergen_info_verified_at');

        foreach ($filters->dietaryTags as $tag) {
            $query->whereExists(fn ($exists) => $exists
                ->selectRaw('1')
                ->from('menu_item_dietary_tags as d')
                ->whereColumn('d.item_id', 'menu_items.id')
                ->where('d.tag', $tag));
        }

        if ($filters->excludeAllergens !== []) {
            $query->whereNotExists(fn ($exists) => $exists
                ->selectRaw('1')
                ->from('menu_item_allergens as a')
                ->whereColumn('a.item_id', 'menu_items.id')
                ->whereIn('a.allergen_code', $filters->excludeAllergens));
        }
    }

    /**
     * @param  list<string>  $tokens
     */
    private function unverifiedCount(MenuFilters $filters, string $phrase, array $tokens): int
    {
        return $this->baseQuery($filters, $phrase, $tokens)
            ->whereNull('menu_items.allergen_info_verified_at')
            ->count('menu_items.id');
    }

    /**
     * @param  list<string>  $tokens
     * @return array{0: string, 1: list<string>}
     */
    private function scoreExpression(string $phrase, array $tokens): array
    {
        $parts = [];
        $bindings = [];
        $add = function (string $column, string $needle, int $points) use (&$parts, &$bindings) {
            $parts[] = "CASE WHEN {$column} LIKE ? ESCAPE '!' THEN {$points} ELSE 0 END";
            $bindings[] = '%'.$this->escapeLike($needle).'%';
        };

        $add('t.search_name', $phrase, 30);
        $add('t.search_keywords', $phrase, 20);
        $add('t.search_description', $phrase, 10);

        foreach ($tokens as $token) {
            $add('t.search_name', $token, 3);
            $add('t.search_keywords', $token, 2);
            $add('t.search_description', $token, 1);
        }

        return ['('.implode(' + ', $parts).')', $bindings];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
