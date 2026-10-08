<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Menu\Actions\CreateMenuItem;
use App\Modules\Menu\Actions\PublishMenuItem;
use App\Modules\Menu\Actions\SaveCategory;
use App\Modules\Menu\Actions\UpdateMenuItem;
use App\Modules\Menu\Actions\VerifyAllergens;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local/testing only: synthetic [DEMO] dishes in id/en/ja. Not the real menu.
 * Covers a verified dish with allergens, a published dish whose verification
 * was cleared by an allergen edit (shown as "not verified"), a sold-out dish,
 * and two chef's picks.
 *
 *   php artisan db:seed --class=MenuDemoSeeder
 */
class MenuDemoSeeder extends Seeder
{
    public function run(SaveCategory $saveCategory, CreateMenuItem $create, UpdateMenuItem $update, VerifyAllergens $verify, PublishMenuItem $publish): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('MenuDemoSeeder only runs in local or testing environments.');
        }

        $this->call(ReferenceSeeder::class);

        $admin = User::query()->active()->withRole(RoleCode::Admin)->orderBy('id')->first();
        if ($admin === null) {
            throw new RuntimeException('Create an admin first (restaurant:create-admin or restaurant:demo-accounts).');
        }

        $categories = [];
        foreach ($this->categories() as $code => $data) {
            $category = MenuCategory::query()->where('code', $code)->first()
                ?? $saveCategory->handle($admin, null, ['code' => $code] + $data);
            $categories[$code] = $category->id;
        }

        foreach ($this->dishes() as $dish) {
            $slug = $dish['slug'];
            if (MenuItem::query()->where('slug', $slug)->exists()) {
                continue;
            }

            $payload = [
                'slug' => $slug,
                'category_id' => $categories[$dish['category']],
                'price' => $dish['price'],
                'is_available' => $dish['available'] ?? true,
                'is_chef_pick' => $dish['chef'] ?? false,
                'spice_level' => $dish['spice'] ?? null,
                'sort_order' => $dish['sort'],
                'translations' => $dish['translations'],
                'allergens' => $dish['allergens'] ?? [],
                'dietary_tags' => $dish['diet'] ?? [],
            ];

            $item = $create->handle($admin, $payload);
            $verify->handle($admin, $item);
            $publish->handle($admin, $item);

            if (isset($dish['then_change_allergens'])) {
                // Editing allergens on a published dish clears the verification.
                $update->handle($admin, $item->refresh(), ['allergens' => $dish['then_change_allergens']] + $payload);
            }
        }
    }

    /**
     * @return array<string, array{sort_order: int, is_active: bool, names: array<string, string>}>
     */
    private function categories(): array
    {
        return [
            'starters' => ['sort_order' => 1, 'is_active' => true, 'names' => ['id' => 'Pembuka', 'en' => 'Starters', 'ja' => '前菜']],
            'mains' => ['sort_order' => 2, 'is_active' => true, 'names' => ['id' => 'Hidangan utama', 'en' => 'Mains', 'ja' => 'メイン']],
            'drinks' => ['sort_order' => 3, 'is_active' => true, 'names' => ['id' => 'Minuman', 'en' => 'Drinks', 'ja' => 'ドリンク']],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dishes(): array
    {
        $t = fn (string $name, string $description, array $aliases = []) => compact('name', 'description', 'aliases');

        return [
            [
                'slug' => 'demo-gado-gado', 'category' => 'starters', 'price' => 32000, 'sort' => 1, 'chef' => true, 'spice' => 1,
                'diet' => ['halal', 'vegetarian'], 'allergens' => ['peanuts' => 'contains', 'soy' => 'contains'],
                'translations' => [
                    'id' => $t('[DEMO] Gado-gado', 'Contoh sintetis: sayuran rebus dengan bumbu kacang dan kerupuk.', ['salad sayur', 'vegetable salad']),
                    'en' => $t('[DEMO] Gado-gado', 'Synthetic sample: steamed vegetables with peanut sauce and crackers.', ['salad', 'ガドガド']),
                    'ja' => $t('[DEMO] ガドガド', '合成サンプル：蒸し野菜にピーナッツソースとクラッカーを添えて。', ['gado gado', 'サラダ']),
                ],
            ],
            [
                'slug' => 'demo-tempe-mendoan', 'category' => 'starters', 'price' => 24000, 'sort' => 2, 'spice' => 0,
                'diet' => ['halal', 'vegan', 'vegetarian'], 'allergens' => ['soy' => 'contains', 'cereals_gluten' => 'contains'],
                'translations' => [
                    'id' => $t('[DEMO] Tempe mendoan', 'Contoh sintetis: tempe tipis goreng berbalut tepung berbumbu.', ['tempe goreng']),
                    'en' => $t('[DEMO] Tempeh mendoan', 'Synthetic sample: thin fried tempeh in a seasoned batter.', ['fried tempeh', 'テンペ']),
                    'ja' => $t('[DEMO] テンペ・ムンドアン', '合成サンプル：薄切りテンペを香味衣で揚げたもの。', ['tempe', '揚げテンペ']),
                ],
            ],
            [
                'slug' => 'demo-nasi-goreng-spesial', 'category' => 'mains', 'price' => 48000, 'sort' => 1, 'chef' => true, 'spice' => 2,
                'diet' => ['halal'], 'allergens' => ['eggs' => 'contains', 'soy' => 'contains', 'crustaceans' => 'may_contain'],
                'translations' => [
                    'id' => $t('[DEMO] Nasi goreng spesial', 'Contoh sintetis: nasi goreng dengan telur, ayam, dan acar.', ['fried rice']),
                    'en' => $t('[DEMO] Special fried rice', 'Synthetic sample: fried rice with egg, chicken, and pickles.', ['nasi goreng', 'チャーハン']),
                    'ja' => $t('[DEMO] スペシャル・ナシゴレン', '合成サンプル：卵、鶏肉、ピクルス入りのインドネシア風チャーハン。', ['ナシゴレン', 'fried rice']),
                ],
            ],
            [
                'slug' => 'demo-rendang-sapi', 'category' => 'mains', 'price' => 68000, 'sort' => 2, 'chef' => true, 'spice' => 2,
                'diet' => ['halal', 'pork_free', 'alcohol_free'], 'allergens' => [],
                'translations' => [
                    'id' => $t('[DEMO] Rendang sapi', 'Contoh sintetis: daging sapi dimasak lama dengan santan dan rempah.', ['rendang']),
                    'en' => $t('[DEMO] Beef rendang', 'Synthetic sample: beef slow-cooked in coconut milk and spices.', ['rendang', 'ルンダン']),
                    'ja' => $t('[DEMO] ビーフ・ルンダン', '合成サンプル：ココナッツミルクとスパイスで煮込んだ牛肉。', ['ルンダン', 'rendang']),
                ],
            ],
            [
                'slug' => 'demo-sate-ayam', 'category' => 'mains', 'price' => 42000, 'sort' => 3, 'spice' => 1,
                'diet' => ['halal'], 'allergens' => ['peanuts' => 'contains', 'soy' => 'contains'],
                'translations' => [
                    'id' => $t('[DEMO] Sate ayam', 'Contoh sintetis: tusuk ayam bakar dengan saus kacang.', ['sate', 'satay']),
                    'en' => $t('[DEMO] Chicken satay', 'Synthetic sample: grilled chicken skewers with peanut sauce.', ['satay', 'サテ']),
                    'ja' => $t('[DEMO] チキンサテ', '合成サンプル：ピーナッツソースを添えた焼き鳥串。', ['サテ', 'satay']),
                ],
            ],
            [
                // Published and verified, then an allergen edit clears the verification.
                'slug' => 'demo-soto-ayam', 'category' => 'mains', 'price' => 38000, 'sort' => 4, 'spice' => 1,
                'diet' => ['halal'], 'allergens' => ['celery' => 'contains'],
                'then_change_allergens' => ['celery' => 'contains', 'eggs' => 'may_contain'],
                'translations' => [
                    'id' => $t('[DEMO] Soto ayam', 'Contoh sintetis: sup ayam kuning dengan soun dan telur rebus.', ['soto']),
                    'en' => $t('[DEMO] Chicken soto', 'Synthetic sample: yellow chicken soup with glass noodles and boiled egg.', ['soto', 'ソト']),
                    'ja' => $t('[DEMO] ソト・アヤム', '合成サンプル：春雨とゆで卵入りの黄色い鶏スープ。', ['ソト', 'soto']),
                ],
            ],
            [
                'slug' => 'demo-es-teler', 'category' => 'drinks', 'price' => 28000, 'sort' => 1, 'available' => false,
                'diet' => ['halal', 'vegetarian'], 'allergens' => ['milk' => 'contains'],
                'translations' => [
                    'id' => $t('[DEMO] Es teler', 'Contoh sintetis: es serut dengan alpukat, kelapa, nangka, dan susu.', ['es campur']),
                    'en' => $t('[DEMO] Es teler', 'Synthetic sample: shaved ice with avocado, coconut, jackfruit, and milk.', ['shaved ice dessert', 'エステレル']),
                    'ja' => $t('[DEMO] エス・テレル', '合成サンプル：アボカド、ココナッツ、ジャックフルーツ、ミルクのかき氷。', ['かき氷', 'es teler']),
                ],
            ],
            [
                'slug' => 'demo-es-teh-manis', 'category' => 'drinks', 'price' => 12000, 'sort' => 2,
                'diet' => ['halal', 'vegan', 'vegetarian', 'alcohol_free', 'pork_free'], 'allergens' => [],
                'translations' => [
                    'id' => $t('[DEMO] Es teh manis', 'Contoh sintetis: teh dingin manis.', ['teh manis', 'iced tea']),
                    'en' => $t('[DEMO] Sweet iced tea', 'Synthetic sample: sweet cold tea.', ['iced tea', 'アイスティー']),
                    'ja' => $t('[DEMO] 甘いアイスティー', '合成サンプル：甘い冷たいお茶。', ['アイスティー', 'es teh']),
                ],
            ],
        ];
    }
}
