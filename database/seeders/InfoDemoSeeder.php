<?php

namespace Database\Seeders;

use App\Models\KnowledgeArticle;
use App\Models\User;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Knowledge\Actions\CreateArticle;
use App\Modules\Knowledge\Actions\PublishRevision;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local/testing only: synthetic [DEMO] info articles in id/en/ja (opening
 * hours, location, policy, FAQ). Not restaurant policy.
 *
 *   php artisan db:seed --class=InfoDemoSeeder
 */
class InfoDemoSeeder extends Seeder
{
    public function run(CreateArticle $create, PublishRevision $publish): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('InfoDemoSeeder only runs in local or testing environments.');
        }

        $this->call(ReferenceSeeder::class);

        $admin = User::query()->active()->withRole(RoleCode::Admin)->orderBy('id')->first();
        if ($admin === null) {
            throw new RuntimeException('Create an admin first (restaurant:create-admin or restaurant:demo-accounts).');
        }

        foreach ($this->articles() as $article) {
            if (KnowledgeArticle::query()->where('slug', $article['slug'])->exists()) {
                continue;
            }

            $created = $create->handle($admin, [
                'slug' => $article['slug'],
                'category' => $article['category'],
                'effective_from' => null,
                'effective_until' => null,
                'translations' => $article['translations'],
            ]);

            $publish->handle($admin, $created);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articles(): array
    {
        $t = fn (string $title, string $body, array $tags = [], array $aliases = []) => compact('title', 'body', 'tags', 'aliases');

        return [
            [
                'slug' => 'demo-opening-hours', 'category' => 'hours',
                'translations' => [
                    'id' => $t('[DEMO] Jam buka', "Contoh sintetis.\n\nSetiap hari 10.00–22.00. Dapur tutup 30 menit sebelum restoran tutup. Informasi ini hanya demo dan bukan jam buka resmi.", ['jam buka'], ['buka jam berapa', 'opening hours', '営業時間']),
                    'en' => $t('[DEMO] Opening hours', "Synthetic sample.\n\nEvery day 10:00–22:00. The kitchen closes 30 minutes before closing time. This is demo content, not the official opening hours.", ['opening hours'], ['open', 'jam buka', '営業時間']),
                    'ja' => $t('[DEMO] 営業時間', "合成サンプルです。\n\n毎日 10:00〜22:00。厨房は閉店の 30 分前に終了します。デモ用の内容であり、公式の営業時間ではありません。", ['営業時間'], ['opening hours', 'jam buka']),
                ],
            ],
            [
                'slug' => 'demo-location', 'category' => 'location',
                'translations' => [
                    'id' => $t('[DEMO] Lokasi dan parkir', "Contoh sintetis.\n\nRestoran contoh berada di Jl. Contoh No. 1, Jakarta. Tersedia parkir motor dan mobil di halaman depan.", ['lokasi', 'parkir'], ['alamat', 'address', '住所']),
                    'en' => $t('[DEMO] Location and parking', "Synthetic sample.\n\nThe sample restaurant is at Jl. Contoh No. 1, Jakarta. Motorbike and car parking is available in the front yard.", ['location', 'parking'], ['address', 'alamat', '住所']),
                    'ja' => $t('[DEMO] 場所と駐車場', "合成サンプルです。\n\nサンプル店舗はジャカルタの Jl. Contoh No. 1 にあります。前庭にバイクと車の駐車場があります。", ['場所', '駐車場'], ['住所', 'address']),
                ],
            ],
            [
                'slug' => 'demo-cancellation-policy', 'category' => 'policy',
                'translations' => [
                    'id' => $t('[DEMO] Kebijakan pembatalan', "Contoh sintetis.\n\nReservasi dapat dibatalkan kapan saja lewat halaman Booking Saya. Mohon batalkan minimal 2 jam sebelumnya agar meja dapat dipakai tamu lain.", ['pembatalan', 'reservasi'], ['batal', 'cancel', 'キャンセル']),
                    'en' => $t('[DEMO] Cancellation policy', "Synthetic sample.\n\nReservations can be cancelled at any time from My Bookings. Please cancel at least 2 hours ahead so the table can go to other guests.", ['cancellation', 'reservation'], ['cancel', 'batal', 'キャンセル']),
                    'ja' => $t('[DEMO] キャンセルポリシー', "合成サンプルです。\n\n予約はいつでも「マイ予約」からキャンセルできます。ほかのお客様がご利用できるよう、2 時間前までのキャンセルをお願いします。", ['キャンセル', '予約'], ['cancel', 'batal']),
                ],
            ],
            [
                'slug' => 'demo-faq-allergy', 'category' => 'faq',
                'translations' => [
                    'id' => $t('[DEMO] FAQ: alergi makanan', "Contoh sintetis.\n\nInformasi alergen di menu hanya ditampilkan bila sudah diverifikasi staf. Jika Anda memiliki alergi, selalu sampaikan kepada staf sebelum memesan.", ['alergi', 'faq'], ['allergy', 'alergen', 'アレルギー']),
                    'en' => $t('[DEMO] FAQ: food allergies', "Synthetic sample.\n\nAllergen information on the menu is only shown once staff have verified it. If you have an allergy, always tell the staff before ordering.", ['allergy', 'faq'], ['allergen', 'alergi', 'アレルギー']),
                    'ja' => $t('[DEMO] FAQ：食物アレルギー', "合成サンプルです。\n\nメニューのアレルゲン情報は、スタッフが確認した場合のみ表示されます。アレルギーがある場合は、ご注文前に必ずスタッフにお伝えください。", ['アレルギー', 'faq'], ['allergen', 'alergi']),
                ],
            ],
        ];
    }
}
